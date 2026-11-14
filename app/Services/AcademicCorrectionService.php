<?php

namespace App\Services;

use App\Models\AcademicRecordCorrection;
use App\Models\ClassAssessmentResult;
use App\Models\ClassAttendanceMark;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcademicCorrectionService
{
    public const REASONS = [
        'recording_error',
        'verified_excuse',
        'reassessment',
        'administrative_review',
    ];

    public function canRequest(User $actor, ClassSection $section): bool
    {
        return app(ClassAttendanceService::class)->canTake($actor, $section);
    }

    public function canReview(User $actor, ClassSection $section): bool
    {
        return app(ClassroomService::class)->canManage($actor, $section->institution)
            && app(ClassroomService::class)->canViewSection($actor, $section);
    }

    private function source(ClassSection $section, string $kind, int $targetId, bool $lock): Model
    {
        $query = match ($kind) {
            'attendance' => ClassAttendanceMark::query()->with('session'),
            'assessment' => ClassAssessmentResult::query()->with('assessment'),
            default => throw ValidationException::withMessages(['kind' => 'Choose attendance or assessment correction.']),
        };

        $query->where('institution_id', $section->institution_id)
            ->where('campus_id', $section->campus_id)
            ->where('class_section_id', $section->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($targetId);
    }

    private function current(Model $source, string $kind): string
    {
        return match ($kind) {
            'attendance' => $source->status,
            'assessment' => $source->points_earned,
        };
    }

    private function isFinal(Model $source, string $kind): bool
    {
        return match ($kind) {
            'attendance' => $source->session?->status === 'approved',
            'assessment' => $source->assessment?->status === 'published',
        };
    }

    private function cents(string $value): int
    {
        if (! preg_match('/^(?:0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['new_value' => 'Enter a nonnegative score with up to two decimals.']);
        }
        $parts = explode('.', $value, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function normalize(Model $source, string $kind, string $value): string
    {
        if ($kind === 'attendance') {
            if (! in_array($value, ClassAttendanceService::STATUSES, true)) {
                throw ValidationException::withMessages(['new_value' => 'Choose a supported attendance status.']);
            }

            return $value;
        }

        $score = $this->cents($value);
        $max = $this->cents((string) $source->assessment->max_points);

        if ($score > $max) {
            throw ValidationException::withMessages(['new_value' => 'Corrected score cannot exceed the assessment maximum.']);
        }

        return intdiv($score, 100).'.'.str_pad((string) ($score % 100), 2, '0', STR_PAD_LEFT);
    }

    public function request(
        User $actor,
        ClassSection $section,
        string $kind,
        int $targetId,
        string $newValue,
        string $reasonCode
    ): AcademicRecordCorrection {
        if (! $this->canRequest($actor, $section)) {
            throw new AuthorizationException('Only the assigned MFA-verified class teacher can request corrections.');
        }

        if (! in_array($reasonCode, self::REASONS, true)) {
            throw ValidationException::withMessages(['reason_code' => 'Choose an approved correction reason.']);
        }

        return DB::transaction(function () use ($actor, $section, $kind, $targetId, $newValue, $reasonCode): AcademicRecordCorrection {
            // Source row first, matching approval lock order; prevents simultaneous requests.
            $source = $this->source($section, $kind, $targetId, true);
            if (! $this->isFinal($source, $kind)) {
                throw ValidationException::withMessages(['correction' => 'Only approved attendance or published results can be corrected.']);
            }

            $normalized = $this->normalize($source, $kind, $newValue);
            $before = $this->current($source, $kind);
            if ($normalized === $before) {
                throw ValidationException::withMessages(['new_value' => 'The requested value is unchanged.']);
            }

            $column = $kind === 'attendance' ? 'attendance_mark_id' : 'assessment_result_id';
            if (AcademicRecordCorrection::query()->where($column, $targetId)
                ->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['correction' => 'A previous correction requires review before another can be requested.']);
            }

            $correction = AcademicRecordCorrection::create([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                $column => $targetId,
                'kind' => $kind,
                'from_value' => $before,
                'to_value' => $normalized,
                'reason_code' => $reasonCode,
                'requested_by' => $actor->id,
            ]);

            app(SecurityAudit::class)->record('academic.correction.requested', $actor, null, $section->institution, [
                'class_section_id' => $section->id,
                'academic_correction_id' => $correction->id,
            ]);

            return $correction->fresh();
        });
    }

    /** No correction is ever applied without separate MFA-enabled institutional approval. */
    public function review(User $actor, ClassSection $section, AcademicRecordCorrection $correction, bool $approve): AcademicRecordCorrection
    {
        if (! $this->canReview($actor, $section)
            || (int) $correction->institution_id !== (int) $section->institution_id
            || (int) $correction->campus_id !== (int) $section->campus_id
            || (int) $correction->class_section_id !== (int) $section->id
            || (int) $correction->requested_by === (int) $actor->id) {
            throw new AuthorizationException('An independent MFA-enabled institution officer must review this correction.');
        }

        return DB::transaction(function () use ($actor, $section, $correction, $approve): AcademicRecordCorrection {
            $kind = $correction->kind;
            if (! in_array($kind, ['attendance', 'assessment'], true)) {
                throw ValidationException::withMessages(['correction' => 'Unsupported correction kind.']);
            }

            $sourceId = $kind === 'attendance' ? $correction->attendance_mark_id : $correction->assessment_result_id;
            if (! $sourceId || ($kind === 'attendance' && $correction->assessment_result_id)
                || ($kind === 'assessment' && $correction->attendance_mark_id)) {
                throw ValidationException::withMessages(['correction' => 'Invalid correction target.']);
            }

            // Lock the source before the request, consistent with creation.
            $source = $this->source($section, $kind, (int) $sourceId, true);
            $locked = AcademicRecordCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['correction' => 'Correction has already been reviewed.']);
            }

            if ($approve) {
                if (! $this->isFinal($source, $kind) || $this->current($source, $kind) !== $locked->from_value) {
                    throw ValidationException::withMessages(['correction' => 'The underlying record has changed; correction needs a fresh review.']);
                }
                $normalized = $this->normalize($source, $kind, $locked->to_value);
                $source->forceFill([
                    $kind === 'attendance' ? 'status' : 'points_earned' => $normalized,
                ])->save();
            }

            $locked->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            app(SecurityAudit::class)->record(
                $approve ? 'academic.correction.approved' : 'academic.correction.rejected',
                $actor,
                null,
                $section->institution,
                ['class_section_id' => $section->id, 'academic_correction_id' => $locked->id]
            );

            return $locked;
        });
    }
}
