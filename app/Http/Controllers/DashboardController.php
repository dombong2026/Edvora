<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionInvitation;
use App\Services\InstitutionAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, InstitutionAccess $access): View|RedirectResponse
    {
        $user = $request->user();

        $memberships = $user->memberships()
            ->where('status', 'active')
            ->with('institution')
            ->get();

        foreach ($memberships as $membership) {
            if ($access->canViewInstitution($user, $membership->institution)) {
                return redirect()->route('dashboard.institution', $membership->institution);
            }
        }

        return $user->isPlatformAdmin()
            ? view('dashboard.platform')
            : view('dashboard.unassigned');
    }

    public function institution(Request $request, Institution $institution, InstitutionAccess $access): View
    {
        Gate::authorize('view', $institution);

        return $this->renderInstitution($request, $institution, $access);
    }

    public function campus(Request $request, Institution $institution, Campus $campus, InstitutionAccess $access): View
    {
        Gate::authorize('view', $institution);
        Gate::authorize('view', $campus);

        return $this->renderInstitution($request, $institution, $access, $campus);
    }

    public function role(Request $request, Institution $institution, AccountRole $role, InstitutionAccess $access): View
    {
        Gate::authorize('view', $institution);

        abort_unless(in_array($role->value, $access->roleCodes($request->user(), $institution), true), 403);

        return $this->renderInstitution($request, $institution, $access, null, $role);
    }

    private function renderInstitution(
        Request $request,
        Institution $institution,
        InstitutionAccess $access,
        ?Campus $selectedCampus = null,
        ?AccountRole $selectedRole = null
    ): View {
        $campuses = $institution->campuses()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (Campus $campus): bool => $access->canViewCampus($request->user(), $campus));

        return view('dashboard.institution', [
            'institution' => $institution,
            'campuses' => $campuses,
            'selectedCampus' => $selectedCampus,
            'selectedRole' => $selectedRole,
            'roleCodes' => $access->roleCodes($request->user(), $institution),
            'canInvite' => $request->user()->roleAssignments()
                ->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn ($q) => $q->whereIn('code', ['administrator', 'director']))
                ->exists(),
            'inviteRoles' => array_filter(AccountRole::cases(), fn (AccountRole $role) => $role !== AccountRole::Admin),
            'pendingInvitations' => InstitutionInvitation::query()->where('institution_id', $institution->id)
                ->whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->latest()->limit(10)->get(),
            'memberCount' => InstitutionMembership::query()
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->count(),
        ]);
    }
}
