<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionInvitation;
use App\Models\Role;
use App\Services\AcademicCatalogService;
use App\Services\ClassroomService;
use App\Services\StudentRegistryService;
use App\Services\InstitutionAccess;
use App\Services\OperationalAccess;
use App\Services\InstitutionEngagement;
use Illuminate\Support\Facades\DB;
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
            ? $this->platform($request, $access)
            : view('dashboard.unassigned');
    }

    public function platform(Request $request, InstitutionAccess $access): View
    {
        $user = $request->user();
        abort_unless($user->isPlatformAdmin(), 403);

        // Only memberships the current administrator holds may be counted.
        $authorizedInstitutionCount = $user->memberships()->where('status', 'active')
            ->with('institution')->get()
            ->filter(fn ($membership): bool => $membership->institution !== null
                && $access->canViewInstitution($user, $membership->institution))
            ->count();

        return view('dashboard.platform', [
            'roleCount' => Role::query()->count(),
            'authorizedInstitutionCount' => $authorizedInstitutionCount,
        ]);
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

        $canInvite = $request->user()->roleAssignments()
            ->where('institution_id', $institution->id)
            ->whereNull('campus_id')
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['administrator', 'director']))
            ->exists();

        $visibleClasses = app(ClassroomService::class)->visible($request->user(), $institution);
        $visibleStudents = app(StudentRegistryService::class)->visible($request->user(), $institution);
        if ($selectedCampus) {
            $visibleClasses = $visibleClasses->where('campus_id', $selectedCampus->id);
            $visibleStudents = $visibleStudents->where('campus_id', $selectedCampus->id);
        }

        $canInviteDirector = $request->user()->roleAssignments()
            ->where('institution_id', $institution->id)->whereNull('campus_id')
            ->whereHas('role', fn ($q) => $q->where('code', 'director'))->exists();

        $availableModules = app(OperationalAccess::class)->modules($request->user(), $institution);
        if ($selectedCampus) {
            $availableModules = array_values(array_filter($availableModules, fn (string $module): bool =>
                app(OperationalAccess::class)->canOperate($request->user(), $institution, $selectedCampus, $module)));
        }
        if ($selectedRole && ! in_array($selectedRole->value, ['director', 'administrator'], true)) {
            $roleModule = match ($selectedRole) {
                AccountRole::Librarian => 'library',
                AccountRole::Cashier => 'cashier',
                AccountRole::Receptionist => 'reception',
                AccountRole::Salesperson => 'admissions',
                AccountRole::Supervisor, AccountRole::Manager,
                AccountRole::Dean, AccountRole::Subdirector => 'operations',
                default => null,
            };
            $availableModules = array_values(array_filter($availableModules, fn (string $module): bool =>
                $module === $roleModule));
        }

        $engagement = app(InstitutionEngagement::class);
        $feed = $engagement->newsFeed($request->user(), $institution, 4);
        if ($selectedCampus) {
            $feed = $feed->filter(fn ($notice): bool =>
                $notice->campus_id === null || (int) $notice->campus_id === (int) $selectedCampus->id)->values();
        }

        $myOpenTickets = DB::table('institution_help_tickets')
            ->where('institution_id', $institution->id)
            ->where('created_by', $request->user()->id)
            ->where('status', '!=', 'closed')
            ->count();

        return view('dashboard.institution', [
            'latestNotices' => $feed,
            'myOpenTickets' => $myOpenTickets,
            'availableModules' => $availableModules,
            'canAccessStaffLeave' => app(\App\Services\StaffWorkflows::class)
                ->eligibleCampuses($request->user(), $institution)->isNotEmpty()
                || app(\App\Services\StaffWorkflows::class)->canReview($request->user(), $institution),
            'canManagePayrollPlanning' => app(\App\Services\StaffWorkflows::class)
                ->canReview($request->user(), $institution),
            'visibleClassCount' => $visibleClasses->count(),
            'visibleStudentCount' => $visibleStudents->count(),
            'canManageAcademics' => app(AcademicCatalogService::class)->canManage($request->user(), $institution),
            'institution' => $institution,
            'campuses' => $campuses,
            'selectedCampus' => $selectedCampus,
            'selectedRole' => $selectedRole,
            'roleCodes' => $access->roleCodes($request->user(), $institution),
            'canInvite' => $canInvite,
            'inviteRoles' => array_filter(AccountRole::cases(), fn (AccountRole $role) =>
                $role !== AccountRole::Admin && ($role !== AccountRole::Director || $canInviteDirector)),
            'pendingInvitations' => $canInvite
                ? InstitutionInvitation::query()->where('institution_id', $institution->id)
                    ->whereNull('accepted_at')->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->latest()->limit(10)->get()
                : collect(),
            'memberCount' => InstitutionMembership::query()
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->count(),
        ]);
    }
}
