<?php

namespace App\Services;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class InstitutionAccess
{
    public function canViewInstitution(User $user, Institution $institution): bool
    {
        if ($institution->status !== 'active') {
            return false;
        }

        return $user->memberships()
            ->where('institution_id', $institution->id)
            ->where('status', 'active')
            ->exists()
            && $user->roleAssignments()->where('institution_id', $institution->id)->exists();
    }

    public function canViewCampus(User $user, Campus $campus): bool
    {
        if ($campus->status !== 'active'
            || ! $this->canViewInstitution($user, $campus->institution)
            || (int) $campus->institution_id !== (int) $campus->institution->id) {
            return false;
        }

        return $user->roleAssignments()
            ->where('institution_id', $campus->institution_id)
            ->where(function (Builder $query) use ($campus): void {
                $query->where('campus_id', $campus->id)
                    ->orWhere(function (Builder $query): void {
                        $query->whereNull('campus_id')
                            ->whereHas('role', fn (Builder $roles) => $roles->whereIn('code', AccountRole::institutionWideCodes()));
                    });
            })
            ->exists();
    }

    /** The assigned role codes visible in an institution; not a platform permission grant. */
    public function roleCodes(User $user, Institution $institution): array
    {
        if (! $this->canViewInstitution($user, $institution)) {
            return [];
        }

        return $user->roleAssignments()
            ->where('institution_id', $institution->id)
            ->with('role:id,code')
            ->get()
            ->pluck('role.code')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
