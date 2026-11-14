<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use App\Services\StaffWorkflows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PayrollPlanningController extends Controller
{
    private function authorize(Request $request, Institution $institution, StaffWorkflows $staff): void
    {
        Gate::authorize('view', $institution);
        abort_unless($staff->canReview($request->user(), $institution), 403);
    }

    public function index(Request $request, Institution $institution, StaffWorkflows $staff): View
    {
        $this->authorize($request, $institution, $staff);
        $data = $request->validate([
            'period' => ['nullable', 'regex:/^20[0-9]{2}-(0[1-9]|1[0-2])$/D'],
        ]);
        $query = DB::table('payroll_plan_entries as plans')
            ->join('users as employees', 'plans.staff_user_id', '=', 'employees.id')
            ->where('plans.institution_id', $institution->id)
            ->select('plans.*', 'employees.name as staff_name');
        if (! empty($data['period'])) {
            $query->where('plans.period', $data['period']);
        }

        return view('dashboard.staff.payroll', [
            'institution' => $institution,
            'period' => $data['period'] ?? '',
            'plans' => $query->orderByDesc('plans.id')->paginate(10)->withQueryString(),
            'campuses' => $staff->eligibleCampuses($request->user(), $institution),
        ]);
    }

    public function store(Request $request, Institution $institution, StaffWorkflows $staff): RedirectResponse
    {
        $this->authorize($request, $institution, $staff);
        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
            'staff_email' => ['required', 'email', 'max:255'],
            'period' => ['required', 'regex:/^20[0-9]{2}-(0[1-9]|1[0-2])$/D'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'gross' => ['required', 'string', 'max:16'],
            'deductions' => ['required', 'string', 'max:16'],
        ]);
        $campus = Campus::query()->where('institution_id', $institution->id)
            ->where('status', 'active')->findOrFail($data['campus_id']);
        $member = User::query()->where('email', mb_strtolower(trim($data['staff_email'])))->firstOrFail();
        $staff->planPayroll($request->user(), $institution, $campus, $member,
            $data['period'], $data['currency'], $data['gross'], $data['deductions']);

        return redirect()->route('staff.payroll.index', $institution)
            ->with('status', 'Internal payroll draft created. No bank transfer, payslip or salary disbursement occurred.');
    }

    public function review(
        Request $request,
        Institution $institution,
        int $plan,
        StaffWorkflows $staff
    ): RedirectResponse {
        $this->authorize($request, $institution, $staff);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'void'])]]);
        $staff->reviewPayroll($request->user(), $institution, $plan, $data['decision']);

        return redirect()->route('staff.payroll.index', $institution)
            ->with('status', 'Payroll planning review saved. This does not authorize or make any payment.');
    }
}
