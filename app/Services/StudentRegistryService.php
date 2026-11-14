<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\StudentGuardianLink;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentRegistryService
{
    private const OFFICERS = ['director', 'administrator', 'dean', 'manager'];
    private const GUARDIAN_OFFICERS = ['director', 'administrator'];

    private function hasRole(User $user, Institution $institution, string $role, ?Campus $campus = null): bool
    {
        return $user->roleAssignments()->where('institution_id', $institution->id)
            ->where('campus_id', $campus?->id)
            ->whereHas('role', fn (Builder $q) => $q->where('code', $role))
            ->exists();
    }

    private function isOfficer(User $actor, Institution $institution, array $roles = self::OFFICERS): bool
    {
        return (bool) $actor->mfa_enabled_at
            && app(InstitutionAccess::class)->canViewInstitution($actor, $institution)
            && $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn (Builder $q) => $q->whereIn('code', $roles))
                ->exists();
    }

    public function canManageCampus(User $actor, Campus $campus): bool
    {
        return $campus->status === 'active' && $campus->institution?->status === 'active'
            && $this->isOfficer($actor, $campus->institution);
    }

    public function canVerifyGuardian(User $actor, StudentProfile $student): bool
    {
        return $student->campus?->status === 'active'
            && $this->isOfficer($actor, $student->institution, self::GUARDIAN_OFFICERS);
    }

    /** Visible list is always restricted to the authenticated identity, linked guardian or MFA-verified officer. */
    public function visible(User $user, Institution $institution): Collection
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return collect();
        }

        $activeCampusIds = $institution->campuses()->where('status', 'active')->pluck('id')->all();
        $ownCampuses = $user->roleAssignments()->where('institution_id', $institution->id)
            ->whereHas('role', fn (Builder $q) => $q->where('code', 'student'))
            ->whereIn('campus_id', $activeCampusIds)->pluck('campus_id')->all();
        $parentCampuses = $user->roleAssignments()->where('institution_id', $institution->id)
            ->whereHas('role', fn (Builder $q) => $q->where('code', 'parent'))
            ->whereIn('campus_id', $activeCampusIds)->pluck('campus_id')->all();

        $officer = $this->isOfficer($user, $institution);

        return StudentProfile::query()->where('institution_id', $institution->id)
            ->whereIn('campus_id', $activeCampusIds)
            ->where(function (Builder $query) use ($officer, $activeCampusIds, $ownCampuses, $parentCampuses, $user): void {
                if ($officer) {
                    $query->orWhereIn('campus_id', $activeCampusIds);
                }
                if ($ownCampuses !== []) {
                    $query->orWhere(function (Builder $nested) use ($user, $ownCampuses): void {
                        $nested->where('user_id', $user->id)->whereIn('campus_id', $ownCampuses);
                    });
                }
                if ($parentCampuses !== []) {
                    $query->orWhere(function (Builder $nested) use ($user, $parentCampuses): void {
                        $nested->whereIn('campus_id', $parentCampuses)
                            ->whereHas('guardians', fn (Builder $link) => $link
                                ->where('guardian_user_id', $user->id)
                                ->whereNotNull('verified_at')
                                ->whereNull('revoked_at'));
                    });
                }
                if (! $officer && $ownCampuses === [] && $parentCampuses === []) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->with('campus:id,name')
            ->orderBy('student_number')
            ->limit(100)->get();
    }

    public function canViewProfile(User $user, StudentProfile $student): bool
    {
        if ($student->campus?->status !== 'active'
            || ! app(InstitutionAccess::class)->canViewInstitution($user, $student->institution)) {
            return false;
        }

        if ($this->isOfficer($user, $student->institution)) {
            return true;
        }

        if ((int) $student->user_id === (int) $user->id
            && $student->user_id !== null
            && $this->hasRole($user, $student->institution, 'student', $student->campus)) {
            return true;
        }

        return $this->hasRole($user, $student->institution, 'parent', $student->campus)
            && $student->guardians()->where('guardian_user_id', $user->id)
                ->whereNotNull('verified_at')->whereNull('revoked_at')->exists();
    }

    public function create(User $actor, Campus $campus, array $data): StudentProfile
    {
        if (! $this->canManageCampus($actor, $campus)) {
            throw new AuthorizationException('Student record creation requires verified institution leadership.');
        }

        return DB::transaction(function () use ($actor, $campus, $data): StudentProfile {
            $profile = StudentProfile::create([
                'institution_id' => $campus->institution_id,
                'campus_id' => $campus->id,
                'student_number' => $data['student_number'],
                'display_name' => $data['display_name'],
                'created_by' => $actor->id,
            ]);

            app(SecurityAudit::class)->record('student.created', $actor, null, $campus->institution, [
                'student_profile_id' => $profile->id, 'campus_id' => $campus->id,
            ]);

            return $profile;
        });
    }

    /** School officers explicitly match an existing student account, never by self-service. */
    public function linkStudentAccount(User $actor, StudentProfile $profile, User $studentUser): void
    {
        if (! $this->canManageCampus($actor, $profile->campus)
            || ! app(InstitutionAccess::class)->canViewCampus($studentUser, $profile->campus)
            || ! $this->hasRole($studentUser, $profile->institution, 'student', $profile->campus)) {
            throw new AuthorizationException('Account must hold an active student role for the same campus.');
        }

        DB::transaction(function () use ($actor, $profile, $studentUser): void {
            $locked = StudentProfile::query()->lockForUpdate()->findOrFail($profile->id);
            if ($locked->user_id !== null && (int) $locked->user_id !== (int) $studentUser->id) {
                throw ValidationException::withMessages(['email' => 'Student record is already linked; contact an administrator.']);
            }
            $other = StudentProfile::query()->where('institution_id', $profile->institution_id)
                ->where('user_id', $studentUser->id)->where('id', '!=', $profile->id)->exists();
            if ($other) {
                throw ValidationException::withMessages(['email' => 'Account is linked to another student record.']);
            }
            if ($locked->user_id === null) {
                $locked->forceFill(['user_id' => $studentUser->id])->save();
                app(SecurityAudit::class)->record('student.account_linked', $actor, $studentUser, $profile->institution, [
                    'student_profile_id' => $profile->id, 'campus_id' => $profile->campus_id,
                ]);
            }
        });
    }

    public function verifyGuardian(User $actor, StudentProfile $student, User $guardian, string $relationship): StudentGuardianLink
    {
        if (! $this->canVerifyGuardian($actor, $student)
            || $actor->id === $guardian->id
            || $student->user_id === $guardian->id
            || ! app(InstitutionAccess::class)->canViewCampus($guardian, $student->campus)
            || ! $this->hasRole($guardian, $student->institution, 'parent', $student->campus)) {
            throw new AuthorizationException('Guardian verification requires an independent officer and active parent campus role.');
        }

        if (! in_array($relationship, ['parent', 'legal_guardian'], true)) {
            throw ValidationException::withMessages(['relationship' => 'Select a supported verified relationship.']);
        }

        return DB::transaction(function () use ($actor, $student, $guardian, $relationship): StudentGuardianLink {
            $existing = StudentGuardianLink::query()
                ->where('student_profile_id', $student->id)
                ->where('guardian_user_id', $guardian->id)->lockForUpdate()->first();
            if ($existing?->revoked_at) {
                throw ValidationException::withMessages(['email' => 'Previously revoked guardian access needs independent re-verification.']);
            }
            if ($existing) {
                return $existing;
            }

            $link = StudentGuardianLink::create([
                'student_profile_id' => $student->id,
                'institution_id' => $student->institution_id,
                'guardian_user_id' => $guardian->id,
                'relationship' => $relationship,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);

            app(SecurityAudit::class)->record('guardian.verified', $actor, $guardian, $student->institution, [
                'student_profile_id' => $student->id, 'relationship' => $relationship,
            ]);

            return $link;
        });
    }

    public function revokeGuardian(User $actor, StudentProfile $student, StudentGuardianLink $link): void
    {
        if (! $this->canVerifyGuardian($actor, $student)
            || (int) $link->student_profile_id !== (int) $student->id
            || (int) $link->institution_id !== (int) $student->institution_id) {
            throw new AuthorizationException('Guardian access revocation requires authorized institutional leadership.');
        }

        DB::transaction(function () use ($actor, $student, $link): void {
            $locked = StudentGuardianLink::query()->lockForUpdate()->findOrFail($link->id);
            if ($locked->revoked_at) {
                return;
            }
            $locked->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            app(SecurityAudit::class)->record('guardian.revoked', $actor, $locked->guardian, $student->institution, [
                'student_profile_id' => $student->id,
            ]);
        });
    }
}
