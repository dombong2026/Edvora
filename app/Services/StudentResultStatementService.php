<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Institution;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentResultStatementService
{
    public function canRead(User $actor, StudentProfile $student): bool
    {
        return app(StudentRegistryService::class)->canViewProfile($actor, $student);
    }

    public function canReview(User $actor, Institution $institution): bool
    {
        return app(StudentLifecycleService::class)->canReview($actor, $institution);
    }

    public function request(User $actor, StudentProfile $student, AcademicYear $year): int
    {
        if (! $this->canRead($actor, $student) || (int) $year->institution_id !== (int) $student->institution_id) {
            throw new AuthorizationException('Student record and academic year must belong to your authorized institution.');
        }

        // An academic statement must have a real institutional enrollment in that year.
        $enrolled = DB::table('class_enrollments as e')
            ->join('class_sections as c', function ($join): void {
                $join->on('c.id', '=', 'e.class_section_id')
                    ->on('c.institution_id', '=', 'e.institution_id')
                    ->on('c.campus_id', '=', 'e.campus_id');
            })
            ->where('e.institution_id', $student->institution_id)
            ->where('e.student_profile_id', $student->id)
            ->where('c.academic_year_id', $year->id)->exists();
        if (! $enrolled) {
            throw ValidationException::withMessages(['academic_year_id' => 'This student has no verified enrollment in the selected year.']);
        }

        return DB::transaction(function () use ($actor, $student, $year): int {
            // Serialize repeated requests for a student's academic year.
            StudentProfile::query()->where('institution_id', $student->institution_id)
                ->lockForUpdate()->findOrFail($student->id);
            $exists = DB::table('student_result_statements')->where('institution_id', $student->institution_id)
                ->where('student_profile_id', $student->id)->where('academic_year_id', $year->id)
                ->whereIn('status', ['pending', 'issued'])->exists();
            if ($exists) {
                throw ValidationException::withMessages(['academic_year_id' => 'This academic year already has a pending or issued statement.']);
            }
            $id = DB::table('student_result_statements')->insertGetId([
                'institution_id' => $student->institution_id,
                'campus_id' => $student->campus_id,
                'student_profile_id' => $student->id,
                'academic_year_id' => $year->id,
                'status' => 'pending', 'requested_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('statement.requested', $actor, null, $student->institution, [
                'student_profile_id' => $student->id, 'statement_id' => $id,
            ]);
            return $id;
        });
    }

    /** Create a point-in-time immutable academic-results snapshot, not a legal certification. */
    public function review(User $actor, StudentProfile $student, int $statementId, string $decision): void
    {
        if (! $this->canReview($actor, $student->institution)) {
            throw new AuthorizationException('An MFA-verified director or administrator must review the statement.');
        }
        if (! in_array($decision, ['issue', 'reject'], true)) {
            throw ValidationException::withMessages(['decision' => 'Select issue or reject.']);
        }

        DB::transaction(function () use ($actor, $student, $statementId, $decision): void {
            $row = DB::table('student_result_statements')
                ->where('institution_id', $student->institution_id)
                ->where('student_profile_id', $student->id)
                ->lockForUpdate()->find($statementId);
            abort_unless($row, 404);
            if ($row->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Only pending statements can be reviewed.']);
            }
            if ((int) $row->requested_by === (int) $actor->id) {
                throw new AuthorizationException('A different officer must review the requested statement.');
            }
            $fields = ['status' => $decision === 'issue' ? 'issued' : 'rejected',
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now()];
            if ($decision === 'issue') {
                $year = AcademicYear::query()->where('institution_id', $student->institution_id)
                    ->findOrFail($row->academic_year_id);
                $results = $this->publishedRows($student, $year->id);
                if ($results->isEmpty()) {
                    throw ValidationException::withMessages(['decision' => 'No independently published results exist for this student and academic year.']);
                }
                $snapshot = json_encode([
                    'schema' => 1,
                    'institution' => $student->institution->name,
                    'campus' => $student->campus->name,
                    'student_number' => $student->student_number,
                    'student_name' => $student->display_name,
                    'academic_year' => $year->name,
                    'issued_at' => now()->toIso8601String(),
                    'results' => $results->map(fn ($r) => [
                        'subject' => $r->subject_name, 'class' => $r->class_name,
                        'assessment' => $r->assessment_title,
                        'points' => (string) $r->points_earned,
                        'maximum' => (string) $r->max_points,
                        'published_at' => (string) $r->published_at,
                    ])->all(),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $fields['statement_number'] = 'EDV-'.str_pad((string) $student->institution_id, 4, '0', STR_PAD_LEFT)
                    .'-'.str_pad((string) $row->id, 8, '0', STR_PAD_LEFT);
                $fields['snapshot_json'] = $snapshot;
                $fields['snapshot_sha256'] = hash('sha256', $snapshot);
            }
            DB::table('student_result_statements')->where('id', $row->id)->update($fields);
            app(SecurityAudit::class)->record(
                $decision === 'issue' ? 'statement.issued' : 'statement.rejected',
                $actor, null, $student->institution,
                ['student_profile_id' => $student->id, 'statement_id' => $row->id]
            );
        });
    }

    public function revoke(User $actor, StudentProfile $student, int $statementId, string $reason): void
    {
        if (! $this->canReview($actor, $student->institution)) {
            throw new AuthorizationException('Statement revocation requires school leadership and MFA.');
        }
        if (mb_strlen(trim($reason)) < 10 || mb_strlen(trim($reason)) > 255) {
            throw ValidationException::withMessages(['reason' => 'Enter a revocation reason between 10 and 255 characters.']);
        }
        DB::transaction(function () use ($actor, $student, $statementId, $reason): void {
            $row = DB::table('student_result_statements')
                ->where('institution_id', $student->institution_id)
                ->where('student_profile_id', $student->id)
                ->lockForUpdate()->find($statementId);
            abort_unless($row, 404);
            if ($row->status !== 'issued') {
                throw ValidationException::withMessages(['statement' => 'Only issued statements can be revoked.']);
            }
            if ((int) $row->reviewed_by === (int) $actor->id) {
                throw new AuthorizationException('A separate authorized officer must revoke an issued statement.');
            }
            DB::table('student_result_statements')->where('id', $row->id)->update([
                'status' => 'revoked', 'revoked_by' => $actor->id,
                'revoked_at' => now(), 'revocation_reason' => trim($reason), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('statement.revoked', $actor, null, $student->institution, [
                'student_profile_id' => $student->id, 'statement_id' => $row->id,
            ]);
        });
    }

    public function snapshot(object $statement): array
    {
        abort_unless($statement->status === 'issued' && $statement->snapshot_json
            && $statement->snapshot_sha256
            && hash_equals($statement->snapshot_sha256, hash('sha256', $statement->snapshot_json)), 409,
            'The issued snapshot is unavailable or fails its integrity check.');
        return json_decode($statement->snapshot_json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Restrict to published results for exactly one student's authorized academic year. */
    private function publishedRows(StudentProfile $student, int $yearId)
    {
        $query = DB::table('class_assessment_results as r')
            ->join('class_assessments as a', function ($join): void {
                $join->on('a.id', '=', 'r.assessment_id')
                    ->on('a.institution_id', '=', 'r.institution_id')
                    ->on('a.campus_id', '=', 'r.campus_id')
                    ->on('a.class_section_id', '=', 'r.class_section_id');
            })
            ->join('class_enrollments as e', function ($join): void {
                $join->on('e.id', '=', 'r.class_enrollment_id')
                    ->on('e.institution_id', '=', 'r.institution_id')
                    ->on('e.campus_id', '=', 'r.campus_id')
                    ->on('e.class_section_id', '=', 'r.class_section_id');
            })
            ->join('class_sections as c', function ($join): void {
                $join->on('c.id', '=', 'a.class_section_id')
                    ->on('c.institution_id', '=', 'a.institution_id')
                    ->on('c.campus_id', '=', 'a.campus_id');
            })
            ->join('academic_subjects as sub', function ($join): void {
                $join->on('sub.id', '=', 'c.academic_subject_id')
                    ->on('sub.institution_id', '=', 'c.institution_id');
            })
            ->where('r.institution_id', $student->institution_id)
            ->where('e.student_profile_id', $student->id)
            ->where('c.academic_year_id', $yearId)
            ->where('a.status', 'published')->whereNotNull('a.published_at')
            ->select('sub.name as subject_name', 'c.name as class_name',
                'a.title as assessment_title', 'r.points_earned', 'a.max_points', 'a.published_at')
            ->orderBy('a.published_at')->orderBy('a.id')->limit(501)->get();
        if ($query->count() > 500) {
            throw ValidationException::withMessages(['decision' => 'More than 500 results require an administrator to subdivide this academic year.']);
        }
        return $query;
    }
}
