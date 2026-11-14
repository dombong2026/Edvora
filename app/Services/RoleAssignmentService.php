<?php

namespace App\Services;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RoleAssignmentService
{
    /** Assign a tenant role only through an authorized institutional officer. */
    public function grant(
        User $actor,
        User $subject,
        Institution $institution,
        AccountRole $role,
        ?Campus $campus = null
    ): InstitutionRoleAssignment {
        if ($role === AccountRole::Admin) {
            throw ValidationException::withMessages(['role' => 'Platform Admin is not an institution role.']);
        }

        $access = app(InstitutionAccess::class);
        if (! $actor->mfa_enabled_at || ! $access->canViewInstitution($actor, $institution)
            || ! $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn ($query) => $query->whereIn('code', ['director', 'administrator']))
                ->exists()) {
            throw new AuthorizationException('Institution role grant not permitted.');
        }

        if ($role === AccountRole::Director
            && ! $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn ($query) => $query->where('code', 'director'))
                ->exists()) {
            throw new AuthorizationException('Only a director may assign a director role.');
        }

        if ($campus && ($campus->institution_id !== $institution->id || $campus->status !== 'active')) {
            throw ValidationException::withMessages(['campus' => 'The campus is not active in this institution.']);
        }

        if (! $subject->memberships()->where('institution_id', $institution->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['user' => 'Active institution membership is required.']);
        }

        $roleRecord = Role::query()->where('code', $role->value)->firstOrFail();

        return DB::transaction(function () use ($actor, $subject, $institution, $campus, $roleRecord): InstitutionRoleAssignment {
            $query = InstitutionRoleAssignment::query()
                ->where('user_id', $subject->id)
                ->where('institution_id', $institution->id)
                ->where('role_id', $roleRecord->id);

            $campus ? $query->where('campus_id', $campus->id) : $query->whereNull('campus_id');

            if ($existing = $query->lockForUpdate()->first()) {
                return $existing;
            }

            $assignment = InstitutionRoleAssignment::create([
                'user_id' => $subject->id,
                'institution_id' => $institution->id,
                'role_id' => $roleRecord->id,
                'campus_id' => $campus?->id,
                'assigned_by' => $actor->id,
            ]);
            app(SecurityAudit::class)->record('role.granted', $actor, $subject, $institution, [
                'role' => $roleRecord->code, 'campus_id' => $campus?->id,
            ]);
            return $assignment;
        });
    }
}
