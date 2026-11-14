<?php

namespace App\Services;

use App\Models\ClassAttendanceMark;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassAttendanceService
{
    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    /** Only the currently assigned MFA-enabled teacher or professor may write attendance. */
    public function canTake(User $user, ClassSection $section): bool
    {
        return $section->status === 'active'
            && $section->year?->status === 'active'
            && $section->teacher_user_id !== null
            && (int) $section->teacher_user_id === (int) $user->id
            && (bool) $user->mfa_enabled_at
            && app(ClassroomService::class)->canViewRoster($user, $section)
            && $user->roleAssignments()->where('institution_id', $section->institution_id)
                ->where('campus_id', $section->campus_id)
                ->whereHas('role', fn (Builder $role) => $role->whereIn('code', ['teacher', 'professor']))
                ->exists();
    }

    public function canApprove(User $user, ClassSection $section): bool
    {
        return app(ClassroomService::class)->canManage($user, $section->institution)
            && app(ClassroomService::class)->canViewSection($user, $section);
    }

    public function create(User $actor, ClassSection $section, string $date): ClassAttendanceSession
    {
        if (! $this->canTake($actor, $section)) {
            throw new AuthorizationException('Only the assigned, MFA-verified teacher may open attendance.');
        }

        $year = $section->year;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || $date < $year->starts_on->toDateString()
            || $date > $year->ends_on->toDateString()
            || $date > now($section->institution->timezone ?? config('app.timezone'))->toDateString()) {
            throw ValidationException::withMessages(['session_date' => 'Attendance date must be within the academic year and cannot be in the future.']);
        }

        return DB::transaction(function () use ($actor, $section, $date): ClassAttendanceSession {
            // One date per class, protected by the database unique constraint.
            $session = ClassAttendanceSession::query()->firstOrCreate([
                'class_section_id' => $section->id,
                'session_date' => $date,
            ], [
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'created_by' => $actor->id,
            ]);

            if ($session->wasRecentlyCreated) {
                app(SecurityAudit::class)->record('attendance.opened', $actor, null, $section->institution, [
                    'class_section_id' => $section->id, 'attendance_session_id' => $session->id,
                ]);
            }

            return $session;
        });
    }

    public function mark(User $actor, ClassAttendanceSession $session, ClassEnrollment $enrollment, string $status): ClassAttendanceMark
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported attendance status.']);
        }

        $section = $session->section;
        if (! $this->canTake($actor, $section)) {
            throw new AuthorizationException('Attendance editing is restricted to the currently assigned teacher.');
        }

        return DB::transaction(function () use ($actor, $session, $enrollment, $status, $section): ClassAttendanceMark {
            $locked = ClassAttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Submitted or approved attendance is locked.']);
            }

            if ((int) $enrollment->class_section_id !== (int) $section->id
                || (int) $enrollment->institution_id !== (int) $section->institution_id
                || (int) $enrollment->campus_id !== (int) $section->campus_id
                || $enrollment->status !== 'active'
                || $enrollment->student?->status !== 'active'
                || $enrollment->enrolled_at?->toDateString() > $locked->session_date->toDateString()) {
                throw ValidationException::withMessages(['enrollment' => 'Only eligible active enrollments may receive attendance marks.']);
            }

            $mark = ClassAttendanceMark::query()->firstOrNew([
                'attendance_session_id' => $locked->id,
                'class_enrollment_id' => $enrollment->id,
            ]);
            $mark->fill([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'status' => $status,
                'recorded_by' => $actor->id,
            ]);
            $mark->save();

            app(SecurityAudit::class)->record('attendance.marked', $actor, null, $section->institution, [
                'class_section_id' => $section->id,
                'attendance_session_id' => $session->id,
            ]);

            return $mark;
        });
    }

    public function submit(User $actor, ClassAttendanceSession $session): void
    {
        $section = $session->section;
        if (! $this->canTake($actor, $section)) {
            throw new AuthorizationException('Only the currently assigned class teacher may submit attendance.');
        }

        DB::transaction(function () use ($actor, $session, $section): void {
            $locked = ClassAttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['attendance' => 'Only draft attendance may be submitted.']);
            }

            $eligible = ClassEnrollment::query()->where('class_section_id', $section->id)
                ->where('status', 'active')
                ->whereDate('enrolled_at', '<=', $locked->session_date->toDateString())
                ->whereHas('student', fn (Builder $q) => $q->where('status', 'active'))
                ->pluck('id');

            if ($eligible->isEmpty()
                || $locked->marks()->count() !== $eligible->count()
                || $locked->marks()->whereIn('class_enrollment_id', $eligible)->count() !== $eligible->count()) {
                throw ValidationException::withMessages(['attendance' => 'Record attendance for every eligible enrolled student before submitting.']);
            }

            $locked->forceFill([
                'status' => 'submitted',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ])->save();

            app(SecurityAudit::class)->record('attendance.submitted', $actor, null, $section->institution, [
                'attendance_session_id' => $session->id, 'class_section_id' => $section->id,
            ]);
        });
    }

    /** Approval requires an independent MFA-enabled institution officer, never the recorder. */
    public function approve(User $actor, ClassAttendanceSession $session): void
    {
        $section = $session->section;
        if (! $this->canApprove($actor, $section)
            || (int) $session->created_by === (int) $actor->id
            || (int) $session->submitted_by === (int) $actor->id) {
            throw new AuthorizationException('Independent school leadership must approve attendance.');
        }

        DB::transaction(function () use ($actor, $session, $section): void {
            $locked = ClassAttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status !== 'submitted'
                || (int) $locked->created_by === (int) $actor->id
                || (int) $locked->submitted_by === (int) $actor->id) {
                throw ValidationException::withMessages(['attendance' => 'Only separately submitted attendance can be approved.']);
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            app(SecurityAudit::class)->record('attendance.approved', $actor, null, $section->institution, [
                'attendance_session_id' => $session->id, 'class_section_id' => $section->id,
            ]);
        });
    }

    /** Parents/students never receive names or statuses of classmates. */
    public function visibleMarks(User $user, ClassAttendanceSession $session): Collection
    {
        $section = $session->section;
        if (! app(ClassroomService::class)->canViewSection($user, $section)) {
            return collect();
        }

        if (app(ClassroomService::class)->canViewRoster($user, $section)) {
            return $session->marks()
                ->with('enrollment.student:id,institution_id,campus_id,user_id,student_number,display_name,status')
                ->orderBy('class_enrollment_id')->limit(150)->get();
        }

        if ($session->status !== 'approved') {
            return collect();
        }

        return $session->marks()->whereHas('enrollment.student', function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereHas('guardians', fn (Builder $link) => $link
                    ->where('guardian_user_id', $user->id)
                    ->whereNotNull('verified_at')->whereNull('revoked_at'));
        })->with('enrollment.student:id,institution_id,campus_id,user_id,student_number,display_name,status')
            ->limit(150)->get()
            ->filter(fn (ClassAttendanceMark $mark): bool =>
                app(StudentRegistryService::class)->canViewProfile($user, $mark->enrollment->student));
    }
}
