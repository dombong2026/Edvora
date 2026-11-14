<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentLifecycleService
{
    public const KINDS = ['promotion', 'transfer', 'withdrawal'];

    public function canRequest(User $actor, StudentProfile $student): bool
    {
        return app(StudentRegistryService::class)->canManageCampus($actor, $student->campus)
            && (int) $student->campus->institution_id === (int) $student->institution_id;
    }

    public function canReview(User $actor, Institution $institution): bool
    {
        return (bool) $actor->mfa_enabled_at
            && app(InstitutionAccess::class)->canViewInstitution($actor, $institution)
            && $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn (Builder $q) => $q->whereIn('code', ['director', 'administrator']))
                ->exists();
    }

    public function request(
        User $actor,
        StudentProfile $student,
        string $kind,
        ?int $targetCampusId,
        ?int $targetSectionId,
        string $reason
    ): int {
        if (! $this->canRequest($actor, $student)) {
            throw new AuthorizationException('Only verified school academic officers can submit changes.');
        }
        if (! in_array($kind, self::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => 'Unsupported student lifecycle change.']);
        }

        return DB::transaction(function () use ($actor, $student, $kind, $targetCampusId, $targetSectionId, $reason): int {
            $locked = StudentProfile::query()->where('institution_id', $student->institution_id)
                ->lockForUpdate()->findOrFail($student->id);
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['student' => 'Only active student profiles may be changed.']);
            }
            if (DB::table('student_lifecycle_requests')->where('student_profile_id', $locked->id)
                ->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['student' => 'Resolve the previous lifecycle request first.']);
            }

            if ($kind === 'promotion') {
                if ($targetCampusId !== null || $targetSectionId === null) {
                    throw ValidationException::withMessages(['target_section_id' => 'Choose one destination class.']);
                }
                $target = DB::table('class_sections')->where('institution_id', $locked->institution_id)
                    ->where('campus_id', $locked->campus_id)
                    ->where('status', 'active')->find($targetSectionId);
                if (! $target) {
                    throw ValidationException::withMessages(['target_section_id' => 'Destination must be an active class at the same campus.']);
                }
                if (DB::table('class_enrollments')->where('student_profile_id', $locked->id)
                    ->where('class_section_id', $target->id)->exists()) {
                    throw ValidationException::withMessages(['target_section_id' => 'Student already has an enrollment for this class.']);
                }
            } elseif ($kind === 'transfer') {
                if ($targetSectionId !== null || $targetCampusId === null
                    || $targetCampusId === (int) $locked->campus_id
                    || ! Campus::query()->where('institution_id', $locked->institution_id)
                        ->where('status', 'active')->whereKey($targetCampusId)->exists()) {
                    throw ValidationException::withMessages(['target_campus_id' => 'Select a different active campus in this institution.']);
                }
            } elseif ($targetSectionId !== null || $targetCampusId !== null) {
                throw ValidationException::withMessages(['kind' => 'Withdrawal does not have a destination.']);
            }

            $now = now();
            $id = DB::table('student_lifecycle_requests')->insertGetId([
                'institution_id' => $locked->institution_id,
                'campus_id' => $locked->campus_id,
                'student_profile_id' => $locked->id,
                'kind' => $kind,
                'target_campus_id' => $targetCampusId,
                'target_section_id' => $targetSectionId,
                'reason' => $reason,
                'requested_by' => $actor->id,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            app(SecurityAudit::class)->record('student.lifecycle.requested', $actor, null, $student->institution, [
                'student_profile_id' => $locked->id, 'campus_id' => $locked->campus_id,
                'lifecycle_request_id' => $id,
            ]);

            return $id;
        });
    }

    /** Promotion and withdrawal change actual records; constrained transfers change only safe unlinked records. */
    public function review(User $actor, Institution $institution, int $requestId, string $decision): void
    {
        if (! $this->canReview($actor, $institution)) {
            throw new AuthorizationException('Only independent MFA-verified school leadership may review.');
        }
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approval or rejection.']);
        }

        DB::transaction(function () use ($actor, $institution, $requestId, $decision): void {
            // Maintain a consistent lock ordering across a school's student lifecycle.
            Institution::query()->whereKey($institution->id)->lockForUpdate()->firstOrFail();
            $request = DB::table('student_lifecycle_requests')
                ->where('institution_id', $institution->id)->lockForUpdate()->find($requestId);
            abort_unless($request, 404);

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
            }
            if ((int) $request->requested_by === (int) $actor->id) {
                throw new AuthorizationException('The requester cannot approve or reject their own change.');
            }
            $student = StudentProfile::query()->where('institution_id', $institution->id)
                ->lockForUpdate()->findOrFail($request->student_profile_id);
            if ((int) $student->campus_id !== (int) $request->campus_id || $student->status !== 'active') {
                throw ValidationException::withMessages(['student' => 'The source record has changed. Review again before proceeding.']);
            }

            $appliedAt = null;
            if ($decision === 'approve') {
                if ($request->kind === 'withdrawal') {
                    DB::table('class_enrollments')->where('institution_id', $institution->id)
                        ->where('student_profile_id', $student->id)
                        ->where('status', 'active')->update(['status' => 'withdrawn', 'updated_at' => now()]);
                    $student->forceFill(['status' => 'withdrawn'])->save();
                    $appliedAt = now();
                } elseif ($request->kind === 'promotion') {
                    $class = DB::table('class_sections')->where('institution_id', $institution->id)
                        ->where('campus_id', $student->campus_id)
                        ->where('status', 'active')->lockForUpdate()->find($request->target_section_id);
                    if (! $class) {
                        throw ValidationException::withMessages(['target_section_id' => 'Destination class is no longer active.']);
                    }
                    if (DB::table('class_enrollments')->where('class_section_id', $class->id)
                        ->where('student_profile_id', $student->id)->exists()) {
                        throw ValidationException::withMessages(['target_section_id' => 'Student already has an enrollment there.']);
                    }
                    $active = DB::table('class_enrollments')->where('class_section_id', $class->id)
                        ->where('status', 'active')->count();
                    if ($active >= $class->capacity) {
                        throw ValidationException::withMessages(['target_section_id' => 'The destination class is full.']);
                    }
                    DB::table('class_enrollments')->insert([
                        'institution_id' => $institution->id,
                        'campus_id' => $student->campus_id,
                        'student_profile_id' => $student->id,
                        'class_section_id' => $class->id,
                        'status' => 'active',
                        'enrolled_by' => $actor->id,
                        'enrolled_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    // Prior enrollment and historic grades are deliberately preserved.
                    $appliedAt = now();
                } elseif ($request->kind === 'transfer') {
                    // Cross-campus enrollment/student/guardian relationships use composite keys.
                    // Never move linked records without first reconciling their access grants.
                    if ($student->user_id !== null
                        || DB::table('student_guardian_links')->where('student_profile_id', $student->id)
                            ->whereNull('revoked_at')->exists()
                        || DB::table('class_enrollments')->where('student_profile_id', $student->id)->exists()
                        || DB::table('student_invoices')->where('student_profile_id', $student->id)->exists()
                        || DB::table('student_result_statements')->where('student_profile_id', $student->id)->exists()) {
                        throw ValidationException::withMessages([
                            'student' => 'Transfer blocked until account roles, guardians, historic enrollments and financial records are independently reconciled.',
                        ]);
                    }
                    $destination = Campus::query()->where('institution_id', $institution->id)
                        ->where('status', 'active')->find($request->target_campus_id);
                    if (! $destination) {
                        throw ValidationException::withMessages(['target_campus_id' => 'Target campus is no longer active.']);
                    }
                    $student->forceFill(['campus_id' => $destination->id])->save();
                    $appliedAt = now();
                } else {
                    throw ValidationException::withMessages(['kind' => 'Unsupported lifecycle action.']);
                }
            }
            DB::table('student_lifecycle_requests')->where('id', $request->id)->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'applied_at' => $appliedAt,
                'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record(
                $decision === 'approve' ? 'student.lifecycle.approved' : 'student.lifecycle.rejected',
                $actor, null, $institution, [
                    'student_profile_id' => $student->id, 'campus_id' => $request->campus_id,
                    'lifecycle_request_id' => $request->id,
                ]
            );
        });
    }
}
