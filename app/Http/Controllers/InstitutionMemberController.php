<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class InstitutionMemberController extends Controller
{
    public function index(Request $request, Institution $institution, RoleAssignmentService $roles): View
    {
        Gate::authorize('view', $institution);
        abort_unless($roles->canManage($request->user(), $institution), 403);

        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
        $search = trim($search);

        $memberships = InstitutionMembership::query()
            ->where('institution_id', $institution->id)
            ->where('status', 'active')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', function ($people) use ($search): void {
                    $people->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->with('user:id,name,email')
            ->orderByDesc('id')->paginate(10)->withQueryString();

        $assignments = InstitutionRoleAssignment::query()
            ->where('institution_id', $institution->id)
            ->whereIn('user_id', $memberships->pluck('user_id'))
            ->with(['role:id,code', 'campus:id,name'])
            ->get()->groupBy('user_id');

        return view('dashboard.members.index', [
            'institution' => $institution,
            'memberships' => $memberships,
            'assignments' => $assignments,
            'search' => $search,
            'campuses' => $institution->campuses()->where('status', 'active')->orderBy('name')->get(),
            'availableRoles' => array_filter(AccountRole::cases(), fn (AccountRole $role) => $role !== AccountRole::Admin),
            'canGrantDirector' => $request->user()->roleAssignments()
                ->where('institution_id', $institution->id)->whereNull('campus_id')
                ->whereHas('role', fn ($q) => $q->where('code', 'director'))->exists(),
        ]);
    }

    public function grant(Request $request, Institution $institution, RoleAssignmentService $roles): RedirectResponse
    {
        Gate::authorize('view', $institution);
        abort_unless($roles->canManage($request->user(), $institution), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', Rule::enum(AccountRole::class)],
            'campus_id' => ['nullable', 'integer'],
        ]);

        $subject = User::query()->whereHas('memberships', fn ($query) => $query
            ->where('institution_id', $institution->id)->where('status', 'active'))
            ->findOrFail($data['user_id']);
        $campus = isset($data['campus_id'])
            ? Campus::query()->where('institution_id', $institution->id)->where('status', 'active')
                ->findOrFail($data['campus_id'])
            : null;

        $roles->grant($request->user(), $subject, $institution, AccountRole::from($data['role']), $campus);

        return redirect()->route('members.index', $institution)
            ->with('status', 'Institution role assigned. The member can now access authorized features.');
    }
}
