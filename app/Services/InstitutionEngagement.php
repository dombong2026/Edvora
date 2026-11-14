<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class InstitutionEngagement
{
    public const HELP_CATEGORIES = [
        'account' => 'Account & access',
        'academic' => 'Academic support',
        'library' => 'Library',
        'operations' => 'School operations',
        'other' => 'Other question',
    ];

    public function canManage(User $user, Institution $institution): bool
    {
        return (bool) $user->mfa_enabled_at
            && app(InstitutionAccess::class)->canViewInstitution($user, $institution)
            && $user->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn (Builder $roles) => $roles->whereIn('code', ['director', 'administrator']))
                ->exists();
    }

    public function campuses(User $user, Institution $institution): Collection
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return collect();
        }

        return $institution->campuses()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (Campus $campus): bool => app(InstitutionAccess::class)->canViewCampus($user, $campus))
            ->values();
    }

    public function canReadTicket(User $user, Institution $institution, object $ticket): bool
    {
        if ((int) $ticket->institution_id !== (int) $institution->id
            || ! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return false;
        }

        if ($this->canManage($user, $institution)) {
            return true;
        }

        $campus = Campus::query()->where('institution_id', $institution->id)
            ->where('status', 'active')->find($ticket->campus_id);

        return $campus !== null
            && (int) $ticket->created_by === (int) $user->id
            && app(InstitutionAccess::class)->canViewCampus($user, $campus);
    }

    public function canReadAnnouncement(User $user, Institution $institution, object $announcement): bool
    {
        if ((int) $announcement->institution_id !== (int) $institution->id
            || ! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return false;
        }

        if ($this->canManage($user, $institution)) {
            return true;
        }

        if ($announcement->status !== 'published' || $announcement->published_at === null) {
            return false;
        }

        if ($announcement->campus_id === null) {
            return true;
        }

        $campus = Campus::query()->where('institution_id', $institution->id)
            ->where('status', 'active')->find($announcement->campus_id);

        return $campus !== null
            && app(InstitutionAccess::class)->canViewCampus($user, $campus);
    }

    public function newsFeed(User $user, Institution $institution, int $limit = 4): Collection
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($user, $institution)) {
            return collect();
        }

        $campusIds = $this->campuses($user, $institution)->pluck('id')->all();

        return DB::table('institution_announcements')
            ->where('institution_id', $institution->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(function ($q) use ($campusIds): void {
                $q->whereNull('campus_id');
                if ($campusIds !== []) {
                    $q->orWhereIn('campus_id', $campusIds);
                }
            })
            ->orderByDesc('published_at')->limit($limit)
            ->get(['id', 'title', 'published_at', 'campus_id']);
    }
}
