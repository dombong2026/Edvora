<?php

namespace App\Services;

use App\Models\ClassAssessment;
use App\Models\ClassAssessmentResult;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassAssessmentService
{
    public function canWrite(User $user, ClassSection $section): bool
    {
        return app(ClassAttendanceService::class)->canTake($user, $section);
    }

    public function canReview(User $user, ClassSection $section): bool
    {
        return app(ClassAttendanceService::class)->canApprove($user, $section);
    }

    /** Convert decimal points to cents without floating-point authorization comparisons. */
    private function cents(string $points): int
    {
        if (! preg_match('/^(?:0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?$/D', $points)) {
            throw ValidationException::withMessages(['points' => 'Enter a nonnegative number with up to two decimal places.']);
        }
        $parts = explode('.', $points, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function create(User $actor, ClassSection $section, array $data): ClassAssessment
    {
        if (! $this->canWrite($actor, $section)) {
            throw new AuthorizationException('Only the assigned, MFA-verified teacher may create an assessment.');
        }

        $maximum = $this->cents((string) $data['max_points']);
        if ($maximum < 1 || $maximum > 1000000) {
            throw ValidationException::withMessages(['max_points' => 'Maximum must be between 0.01 and 10,000 points.']);
        }

        return DB::transaction(function () use ($actor, $section, $data, $maximum): ClassAssessment {
            $assessment = ClassAssessment::create([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'code' => $data['code'],
                'title' => $data['title'],
                'max_points' => $this->decimal($maximum),
                'created_by' => $actor->id,
            ]);

            app(SecurityAudit::class)->record('assessment.created', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'assessment_id' => $assessment->id,
            ]);

            return $assessment;
        });
    }

    public function record(User $actor, ClassAssessment $assessment, ClassEnrollment $enrollment, string $points): ClassAssessmentResult
    {
        $section = $assessment->section;
        if (! $this->canWrite($actor, $section)) {
            throw new AuthorizationException('Only the verified assigned teacher may record a score.');
        }

        $score = $this->cents($points);
        $maximum = $this->cents($assessment->max_points);
        if ($score > $maximum) {
            throw ValidationException::withMessages(['points_earned' => 'Score exceeds the assessment maximum.']);
        }

        return DB::transaction(function () use ($actor, $assessment, $enrollment, $section, $score): ClassAssessmentResult {
            $locked = ClassAssessment::query()->lockForUpdate()->findOrFail($assessment->id);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['result' => 'Submitted or published assessment results cannot be edited.']);
            }

            if ((int) $enrollment->class_section_id !== (int) $section->id
                || (int) $enrollment->institution_id !== (int) $section->institution_id
                || (int) $enrollment->campus_id !== (int) $section->campus_id
                || $enrollment->status !== 'active'
                || $enrollment->student?->status !== 'active') {
                throw ValidationException::withMessages(['enrollment_id' => 'Only active enrolled students from this class may receive scores.']);
            }

            $result = ClassAssessmentResult::query()->firstOrNew([
                'assessment_id' => $locked->id,
                'class_enrollment_id' => $enrollment->id,
            ]);

            $result->fill([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'points_earned' => $this->decimal($score),
                'recorded_by' => $actor->id,
            ]);
            $result->save();

            // Never include a student's score or name in general security event metadata.
            app(SecurityAudit::class)->record('assessment.result.recorded', $actor, null, $section->institution, [
                'assessment_id' => $assessment->id, 'class_section_id' => $section->id,
            ]);

            return $result;
        });
    }

    public function submit(User $actor, ClassAssessment $assessment): void
    {
        $section = $assessment->section;
        if (! $this->canWrite($actor, $section)) {
            throw new AuthorizationException('Only the assigned teacher may submit grade drafts.');
        }

        DB::transaction(function () use ($actor, $assessment, $section): void {
            $locked = ClassAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['assessment' => 'Only draft assessments may be submitted.']);
            }

            $eligible = ClassEnrollment::query()->where('class_section_id', $section->id)
                ->where('status', 'active')
                ->whereHas('student', fn (Builder $q) => $q->where('status', 'active'))
                ->pluck('id');

            if ($eligible->isEmpty()
                || $locked->results()->count() !== $eligible->count()
                || $locked->results()->whereIn('class_enrollment_id', $eligible)->count() !== $eligible->count()) {
                throw ValidationException::withMessages(['assessment' => 'Enter an explicit score for every active enrolled student before submitting.']);
            }

            $locked->forceFill([
                'status' => 'submitted',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ])->save();

            app(SecurityAudit::class)->record('assessment.submitted', $actor, null, $section->institution, [
                'assessment_id' => $locked->id, 'class_section_id' => $section->id,
            ]);
        });
    }

    public function publish(User $actor, ClassAssessment $assessment): void
    {
        $section = $assessment->section;
        if (! $this->canReview($actor, $section)
            || (int) $assessment->created_by === (int) $actor->id
            || (int) $assessment->submitted_by === (int) $actor->id) {
            throw new AuthorizationException('An independent MFA-enabled school officer must approve grades.');
        }

        DB::transaction(function () use ($actor, $assessment, $section): void {
            $locked = ClassAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ($locked->status !== 'submitted'
                || (int) $locked->created_by === (int) $actor->id
                || (int) $locked->submitted_by === (int) $actor->id) {
                throw ValidationException::withMessages(['assessment' => 'Only independently submitted results may be published.']);
            }

            $locked->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            app(SecurityAudit::class)->record('assessment.published', $actor, null, $section->institution, [
                'assessment_id' => $locked->id, 'class_section_id' => $section->id,
            ]);
        });
    }

    /** Staff can view permitted gradebook; each family/student receives only linked published rows. */
    public function visibleResults(User $user, ClassAssessment $assessment): Collection
    {
        $section = $assessment->section;
        if (! app(ClassroomService::class)->canViewSection($user, $section)) {
            return collect();
        }

        if ($this->canWrite($user, $section) || $this->canReview($user, $section)) {
            return $assessment->results()
                ->with('enrollment.student:id,institution_id,campus_id,user_id,student_number,display_name,status')
                ->orderBy('class_enrollment_id')->limit(150)->get();
        }

        if ($assessment->status !== 'published') {
            return collect();
        }

        return $assessment->results()->whereHas('enrollment.student', function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereHas('guardians', fn (Builder $link) => $link
                    ->where('guardian_user_id', $user->id)
                    ->whereNotNull('verified_at')->whereNull('revoked_at'));
        })->with('enrollment.student:id,institution_id,campus_id,user_id,student_number,display_name,status')
            ->limit(150)->get()
            ->filter(fn (ClassAssessmentResult $result): bool =>
                app(StudentRegistryService::class)->canViewProfile($user, $result->enrollment->student));
    }
}
