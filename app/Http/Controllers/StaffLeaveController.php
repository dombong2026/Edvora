<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Institution;
use App\Services\StaffWorkflows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class StaffLeaveController extends Controller
{
    public function index(Request $request, Institution $institution, StaffWorkflows $staff): View
    {
        Gate::authorize('view', $institution);
        $canReview = $staff->canReview($request->user(), $institution);
        $campuses = $staff->eligibleCampuses($request->user(), $institution);
        abort_if(! $canReview && $campuses->isEmpty(), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
        ]);

        $query = DB::table('staff_leave_requests as leaves')
            ->join('users as staff', 'leaves.staff_user_id', '=', 'staff.id')
            ->where('leaves.institution_id', $institution->id)
            ->select('leaves.*', 'staff.name as staff_name');
        if (! $canReview) {
            $query->where('leaves.staff_user_id', $request->user()->id);
        }
        if (! empty($data['status'])) {
            $query->where('leaves.status', $data['status']);
        }

        return view('dashboard.staff.leave', [
            'institution' => $institution,
            'canReview' => $canReview,
            'campuses' => $campuses,
            'statusFilter' => $data['status'] ?? '',
            'requests' => $query->orderByDesc('leaves.id')->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request, Institution $institution, StaffWorkflows $staff): RedirectResponse
    {
        Gate::authorize('view', $institution);
        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(['annual', 'personal', 'other'])],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $campus = Campus::query()->where('institution_id', $institution->id)
            ->where('status', 'active')->findOrFail($data['campus_id']);

        $staff->requestLeave($request->user(), $institution, $campus,
            $data['category'], $data['starts_on'], $data['ends_on']);

        return redirect()->route('staff.leave.index', $institution)
            ->with('status', 'Your leave request was saved for independent school review.');
    }

    public function review(
        Request $request,
        Institution $institution,
        int $leave,
        StaffWorkflows $staff
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $staff->reviewLeave($request->user(), $institution, $leave, $data['decision']);

        return redirect()->route('staff.leave.index', $institution)
            ->with('status', 'Leave review decision recorded.');
    }

    public function cancel(
        Request $request,
        Institution $institution,
        int $leave,
        StaffWorkflows $staff
    ): RedirectResponse {
        Gate::authorize('view', $institution);
        $staff->cancelLeave($request->user(), $institution, $leave);

        return redirect()->route('staff.leave.index', $institution)
            ->with('status', 'Pending leave request cancelled.');
    }
}
