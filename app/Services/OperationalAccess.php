<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * An operational role is a separate, explicit grant. Platform-admin status
 * does not grant institution or campus access.
 */
final class OperationalAccess
{
    public const TITLES = [
        'library' => 'Library circulation',
        'cashier' => 'Fee proposals',
        'reception' => 'Reception desk',
        'admissions' => 'Admissions enquiries',
        'operations' => 'Operations task board',
    ];

    public const ICONS = [
        'library' => 'academic',
        'cashier' => 'security',
        'reception' => 'members',
        'admissions' => 'students',
        'operations' => 'classrooms',
    ];

    private const SPECIALISTS = [
        'library' => ['librarian'],
        'cashier' => ['cashier'],
        'reception' => ['receptionist'],
        'admissions' => ['salesperson'],
        'operations' => ['supervisor', 'manager', 'dean', 'subdirector'],
    ];

    private const LEADERS = ['director', 'administrator'];

    public function canOperate(User $actor, Institution $institution, Campus $campus, string $module): bool
    {
        if (! isset(self::SPECIALISTS[$module])
            || ! $actor->mfa_enabled_at
            || (int) $campus->institution_id !== (int) $institution->id
            || ! app(InstitutionAccess::class)->canViewCampus($actor, $campus)) {
            return false;
        }

        $specialists = self::SPECIALISTS[$module];
        $allowed = array_values(array_unique(array_merge($specialists, self::LEADERS)));

        return $actor->roleAssignments()
            ->where('institution_id', $institution->id)
            ->where(function (Builder $query) use ($campus, $allowed, $module): void {
                $query->where(function (Builder $scoped) use ($campus, $allowed): void {
                    $scoped->where('campus_id', $campus->id)
                        ->whereHas('role', fn (Builder $roles) => $roles->whereIn('code', $allowed));
                })->orWhere(function (Builder $wide) use ($allowed, $module): void {
                    $wide->whereNull('campus_id')
                        ->whereHas('role', fn (Builder $roles) => $roles->whereIn(
                            'code',
                            $module === 'operations' ? $allowed : self::LEADERS
                        ));
                });
            })->exists();
    }

    /** @return Collection<int, Campus> */
    public function campuses(User $actor, Institution $institution, string $module): Collection
    {
        if (! isset(self::SPECIALISTS[$module]) || ! $actor->mfa_enabled_at
            || ! app(InstitutionAccess::class)->canViewInstitution($actor, $institution)) {
            return collect();
        }

        return $institution->campuses()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (Campus $campus): bool => $this->canOperate($actor, $institution, $campus, $module))
            ->values();
    }

    public function modules(User $actor, Institution $institution): array
    {
        $allowed = [];
        foreach (array_keys(self::TITLES) as $module) {
            if ($this->campuses($actor, $institution, $module)->isNotEmpty()) {
                $allowed[] = $module;
            }
        }

        return $allowed;
    }

    public function canReviewFees(User $actor, Institution $institution, Campus $campus): bool
    {
        return $this->canOperate($actor, $institution, $campus, 'cashier')
            && $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn (Builder $q) => $q->whereIn('code', self::LEADERS))
                ->exists();
    }
}
