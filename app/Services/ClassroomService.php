<?php

namespace App\Services;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\ClassEnrollment;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassroomService
{
    private function hasCampusRole(User $user, ClassSection $section, array $roles): bool
    {
        return $user->roleAssignments()->where('institution_id', $section->institution_id)
            ->where('campus_id', $section->campus_id)
            ->whereHas('role', fn (Builder $q) => $q->whereIn('code', $roles))
            ->exists();
    }

    private function campusRoleIds(User $user, Institution $institution, array $roles): array
    {
        return $user->roleAssignments()->where('institution_id', $institution->id)
            ->whereHas('role', fn (Builder $q) => $q->whereIn('code', $roles))
            ->whereNotNull('campus_id')->pluck('campus_id')->all();
    }

    public function canManage(User $user, Institution $institution): bool
    {
        return app(AcademicCatalogService::class)->canManage($user, $institution);
    }

    private function assignedTeacher(User $user, ClassSection $section): bool
    {
        return (bool) $user->mfa_enabled_at
            && $section->teacher_user_id !== null
            && (int) $section->teacher_user_id === (int) $user->id
            && $this->hasCampusRole($user, $section, ['teacher', 'professor']);
    }

    public function canViewSection(User $user, ClassSection $section): bool
    {
        if ($section->campus?->status !== 'active'
            || $section->institution?->status !== 'active'
            || ! app(InstitutionAccess::class)->canViewCampus($user, $section->campus)) {
            return false;
        }

        if ($this->canManage($user, $section->institution) || $this->assignedTeacher($user, $section)) {
            return true;
        }

        if ($section->status !== 'active') {
            return false;
        }

        if ($this->hasCampusRole($user, $section, ['student'])
            && $section->enrollments()->where('status', 'active')
                ->whereHas('student', fn (Builder $q) => $q->where('user_id', $user->id))
                ->exists()) {
            return true;
        }

        return $this->hasCampusRole($user, $section, ['parent'])
            && $section->enrollments()->where('status', 'active')
                ->whereHas('student.guardians', fn (Builder $q) => $q
                    ->where('guardian_user_id', $user->id)
                    ->whereNotNull('verified_at')
                    ->whereNull('revoked_at'))
                ->exists();
    }

    public function canViewRoster(User $user, ClassSection $section): bool
    {
        return $this->canViewSection($user, $section)
            && ($this->canManage($user, $section->institution) || $this->assignedTeacher($user, $section));
    }

    /** Applies institution, campus and ownership filters before fetching any records. */
    public function visible(User $user, Institution $institution): Collection
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return collect();
        }

        $activeCampuses = $institution->campuses()->where('status', 'active')->pluck('id')->all();
        $officer = $this->canManage($user, $institution);
        $teacherCampuses = array_intersect($activeCampuses, $this->campusRoleIds($user, $institution, ['teacher', 'professor']));
        $studentCampuses = array_intersect($activeCampuses, $this->campusRoleIds($user, $institution, ['student']));
        $parentCampuses = array_intersect($activeCampuses, $this->campusRoleIds($user, $institution, ['parent']));

        return ClassSection::query()
            ->where('institution_id', $institution->id)
            ->whereIn('campus_id', $activeCampuses)
            ->where(function (Builder $query) use (
                $officer, $activeCampuses, $teacherCampuses,
                $studentCampuses, $parentCampuses, $user
            ): void {
                if ($officer) {
                    $query->orWhereIn('campus_id', $activeCampuses);
                }
                if ($teacherCampuses !== [] && $user->mfa_enabled_at) {
                    $query->orWhere(function (Builder $nested) use ($teacherCampuses, $user): void {
                        $nested->where('teacher_user_id', $user->id)
                            ->whereIn('campus_id', $teacherCampuses);
                    });
                }
                if ($studentCampuses !== []) {
                    $query->orWhere(function (Builder $nested) use ($studentCampuses, $user): void {
                        $nested->where('status', 'active')
                            ->whereIn('campus_id', $studentCampuses)
                            ->whereHas('enrollments', fn (Builder $enrollment) => $enrollment
                                ->where('status', 'active')
                                ->whereHas('student', fn (Builder $student) => $student->where('user_id', $user->id)));
                    });
                }
                if ($parentCampuses !== []) {
                    $query->orWhere(function (Builder $nested) use ($parentCampuses, $user): void {
                        $nested->where('status', 'active')
                            ->whereIn('campus_id', $parentCampuses)
                            ->whereHas('enrollments', fn (Builder $enrollment) => $enrollment
                                ->where('status', 'active')
                                ->whereHas('student.guardians', fn (Builder $link) => $link
                                    ->where('guardian_user_id', $user->id)
                                    ->whereNotNull('verified_at')->whereNull('revoked_at')));
                    });
                }
                if (! $officer && $teacherCampuses === [] && $studentCampuses === [] && $parentCampuses === []) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->with(['campus', 'year', 'subject', 'teacher'])
            ->orderBy('code')->limit(100)->get()
            ->filter(fn (ClassSection $section): bool => $this->canViewSection($user, $section));
    }

    public function create(
        User $actor,
        Campus $campus,
        AcademicYear $year,
        AcademicSubject $subject,
        array $data
    ): ClassSection {
        if (! $this->canManage($actor, $campus->institution)
            || $campus->status !== 'active'
            || (int) $year->institution_id !== (int) $campus->institution_id
            || $year->status !== 'active'
            || (int) $subject->institution_id !== (int) $campus->institution_id
            || ! $subject->is_active) {
            throw new AuthorizationException('Active academic year, subject, campus and institution leadership required.');
        }

        return DB::transaction(function () use ($actor, $campus, $year, $subject, $data): ClassSection {
            $section = ClassSection::create([
                'institution_id' => $campus->institution_id,
                'campus_id' => $campus->id,
                'academic_year_id' => $year->id,
                'academic_subject_id' => $subject->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'capacity' => $data['capacity'],
                'created_by' => $actor->id,
            ]);

            app(SecurityAudit::class)->record('class.section.created', $actor, null, $campus->institution, [
                'class_section_id' => $section->id, 'campus_id' => $campus->id, 'academic_year_id' => $year->id,
            ]);

            return $section;
        });
    }

    public function assignTeacher(User $actor, ClassSection $section, User $teacher): void
    {
        if (! $this->canManage($actor, $section->institution)
            || $section->campus?->status !== 'active'
            || ! app(InstitutionAccess::class)->canViewCampus($teacher, $section->campus)
            || ! $this->hasCampusRole($teacher, $section, ['teacher', 'professor'])) {
            throw new AuthorizationException('Teacher must have an active teaching role in this campus.');
        }

        DB::transaction(function () use ($actor, $section, $teacher): void {
            $locked = ClassSection::query()->lockForUpdate()->findOrFail($section->id);
            $locked->forceFill(['teacher_user_id' => $teacher->id])->save();
            app(SecurityAudit::class)->record('class.teacher.assigned', $actor, $teacher, $section->institution, [
                'class_section_id' => $section->id, 'campus_id' => $section->campus_id,
            ]);
        });
    }

    public function activate(User $actor, ClassSection $section): void
    {
        if (! $this->canManage($actor, $section->institution)) {
            throw new AuthorizationException('Class activation requires authorized leadership.');
        }

        DB::transaction(function () use ($actor, $section): void {
            $locked = ClassSection::query()->lockForUpdate()->findOrFail($section->id);
            $locked->load(['campus', 'year', 'subject', 'teacher', 'institution']);
            if ($locked->status !== 'draft' || $locked->campus->status !== 'active'
                || $locked->year->status !== 'active' || ! $locked->subject->is_active
                || ! $locked->teacher
                || ! app(InstitutionAccess::class)->canViewCampus($locked->teacher, $locked->campus)
                || ! $this->hasCampusRole($locked->teacher, $locked, ['teacher', 'professor'])) {
                throw ValidationException::withMessages(['section' => 'An active school year, subject, campus and authorized teacher assignment are required.']);
            }
            $locked->forceFill(['status' => 'active'])->save();
            app(SecurityAudit::class)->record('class.section.activated', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'campus_id' => $section->campus_id,
            ]);
        });
    }

    public function enroll(User $actor, ClassSection $section, StudentProfile $student): ClassEnrollment
    {
        if (! $this->canManage($actor, $section->institution)) {
            throw new AuthorizationException('Class enrollment requires authorized leadership.');
        }

        return DB::transaction(function () use ($actor, $section, $student): ClassEnrollment {
            $locked = ClassSection::query()->lockForUpdate()->with('year')->findOrFail($section->id);
            if ($locked->status !== 'active' || $locked->year->status !== 'active'
                || $student->status !== 'active'
                || $student->campus?->status !== 'active'
                || (int) $student->institution_id !== (int) $section->institution_id
                || (int) $student->campus_id !== (int) $section->campus_id) {
                throw ValidationException::withMessages(['student' => 'Student must be active and in the same institution and campus as an active class.']);
            }

            $existing = ClassEnrollment::query()->where('class_section_id', $section->id)
                ->where('student_profile_id', $student->id)->first();
            if ($existing?->status === 'active') {
                return $existing;
            }
            if ($existing) {
                throw ValidationException::withMessages(['student' => 'Previously withdrawn enrollment requires reviewed reinstatement.']);
            }

            if ($locked->enrollments()->where('status', 'active')->count() >= $locked->capacity) {
                throw ValidationException::withMessages(['student' => 'Class capacity reached.']);
            }

            $enrollment = ClassEnrollment::create([
                'institution_id' => $section->institution_id,
                'campus_id' => $section->campus_id,
                'class_section_id' => $section->id,
                'student_profile_id' => $student->id,
                'enrolled_by' => $actor->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
            app(SecurityAudit::class)->record('class.student.enrolled', $actor, null, $section->institution, [
                'class_section_id' => $section->id, 'student_profile_id' => $student->id,
            ]);

            return $enrollment;
        });
    }
}
