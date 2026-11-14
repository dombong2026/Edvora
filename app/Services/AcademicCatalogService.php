<?php

namespace App\Services;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class AcademicCatalogService
{
    public function canManage(User $actor, Institution $institution): bool
    {
        return (bool) $actor->mfa_enabled_at
            && app(InstitutionAccess::class)->canViewInstitution($actor, $institution)
            && $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn ($q) => $q->whereIn('code', [
                    'director', 'administrator', 'dean', 'manager',
                ]))
                ->exists();
    }

    public function createYear(User $actor, Institution $institution, array $data): AcademicYear
    {
        if (! $this->canManage($actor, $institution)) {
            throw new AuthorizationException('Academic year management requires authorized leadership and MFA.');
        }

        return AcademicYear::create([
            'institution_id' => $institution->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'created_by' => $actor->id,
        ]);
    }

    public function createSubject(User $actor, Institution $institution, array $data): AcademicSubject
    {
        if (! $this->canManage($actor, $institution)) {
            throw new AuthorizationException('Subject management requires authorized leadership and MFA.');
        }

        return AcademicSubject::create([
            'institution_id' => $institution->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by' => $actor->id,
        ]);
    }
    public function activateYear(User $actor, Institution $institution, AcademicYear $year): void
    {
        if (! $this->canManage($actor, $institution)
            || (int) $year->institution_id !== (int) $institution->id) {
            throw new AuthorizationException('Academic year activation is restricted to authorized leadership.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $institution, $year): void {
            $locked = AcademicYear::query()->lockForUpdate()->findOrFail($year->id);
            if ($locked->status !== 'draft') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'year' => 'Only a draft academic year can be activated.',
                ]);
            }
            $locked->forceFill(['status' => 'active'])->save();
            app(SecurityAudit::class)->record('academics.year.activated', $actor, null, $institution, [
                'academic_year_id' => $year->id,
            ]);
        });
    }
}
