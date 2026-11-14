<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StaffWorkflows
{
    public const STAFF_ROLES = [
        'director', 'administrator', 'subdirector', 'manager', 'dean', 'supervisor',
        'teacher', 'professor', 'librarian', 'receptionist', 'cashier', 'salesperson',
    ];

    public function canReview(User $actor, Institution $institution): bool
    {
        return (bool) $actor->mfa_enabled_at
            && app(InstitutionAccess::class)->canViewInstitution($actor, $institution)
            && $actor->roleAssignments()->where('institution_id', $institution->id)
                ->whereNull('campus_id')
                ->whereHas('role', fn (Builder $q) => $q->whereIn('code', ['director', 'administrator']))->exists();
    }

    public function canRequest(User $actor, Institution $institution, Campus $campus): bool
    {
        return (int) $campus->institution_id === (int) $institution->id
            && app(InstitutionAccess::class)->canViewCampus($actor, $campus)
            && $actor->roleAssignments()->where('institution_id', $institution->id)
                ->where(function (Builder $q) use ($campus): void {
                    $q->where('campus_id', $campus->id)
                        ->orWhere(function (Builder $w): void {
                            $w->whereNull('campus_id')->whereHas('role',
                                fn (Builder $r) => $r->whereIn('code', [
                                    'director', 'administrator', 'subdirector', 'manager', 'dean', 'supervisor',
                                ]));
                        });
                })->whereHas('role', fn (Builder $r) => $r->whereIn('code', self::STAFF_ROLES))->exists();
    }

    public function eligibleCampuses(User $actor, Institution $institution)
    {
        if (! app(InstitutionAccess::class)->canViewInstitution($actor, $institution)) {
            return collect();
        }
        return $institution->campuses()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (Campus $campus): bool => $this->canRequest($actor, $institution, $campus))->values();
    }

    public function requestLeave(User $actor, Institution $institution, Campus $campus, string $category, string $start, string $end): int
    {
        if (! $this->canRequest($actor, $institution, $campus)) {
            throw new AuthorizationException('You may request leave only at a verified staff campus.');
        }
        if (! in_array($category, ['annual', 'personal', 'other'], true)) {
            throw ValidationException::withMessages(['category' => 'Unsupported leave category.']);
        }
        $starts = \Carbon\CarbonImmutable::parse($start);
        $ends = \Carbon\CarbonImmutable::parse($end);
        if ($starts->gt($ends) || $ends->diffInDays($starts) > 30) {
            throw ValidationException::withMessages(['ends_on' => 'Leave must be 1–31 days.']);
        }

        return DB::transaction(function () use ($actor, $institution, $campus, $category, $start, $end): int {
            $overlap = DB::table('staff_leave_requests')->where('institution_id', $institution->id)
                ->where('staff_user_id', $actor->id)->whereIn('status', ['pending', 'approved'])
                ->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'An overlapping pending or approved leave request exists.']);
            }
            $id = DB::table('staff_leave_requests')->insertGetId([
                'institution_id' => $institution->id, 'campus_id' => $campus->id,
                'staff_user_id' => $actor->id, 'category' => $category,
                'starts_on' => $start, 'ends_on' => $end, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('staff.leave.requested', $actor, null, $institution, [
                'campus_id' => $campus->id, 'staff_leave_id' => $id,
            ]);
            return $id;
        });
    }

    public function reviewLeave(User $actor, Institution $institution, int $id, string $decision): void
    {
        if (! $this->canReview($actor, $institution)) {
            throw new AuthorizationException('Leave review requires MFA-verified school leadership.');
        }
        DB::transaction(function () use ($actor, $institution, $id, $decision): void {
            $record = DB::table('staff_leave_requests')->where('institution_id', $institution->id)
                ->lockForUpdate()->find($id);
            abort_unless($record, 404);
            if ($record->status !== 'pending' || ! in_array($decision, ['approve', 'reject'], true)) {
                throw ValidationException::withMessages(['decision' => 'Only pending leave may be reviewed.']);
            }
            if ((int) $record->staff_user_id === (int) $actor->id) {
                throw new AuthorizationException('You cannot review your own leave request.');
            }
            DB::table('staff_leave_requests')->where('id', $id)->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            app(InternalInbox::class)->leaveEvent(
                $institution, $id, $record->staff_user_id,
                $decision === 'approve' ? 'approved' : 'rejected'
            );
            app(SecurityAudit::class)->record('staff.leave.reviewed', $actor, null, $institution, [
                'staff_leave_id' => $id, 'campus_id' => $record->campus_id,
            ]);
        });
    }

    public function cancelLeave(User $actor, Institution $institution, int $id): void
    {
        DB::transaction(function () use ($actor, $institution, $id): void {
            $record = DB::table('staff_leave_requests')->where('institution_id', $institution->id)
                ->where('staff_user_id', $actor->id)->lockForUpdate()->find($id);
            abort_unless($record, 404);
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['leave' => 'Only pending requests can be cancelled.']);
            }
            if (! $this->canRequest($actor, $institution, Campus::findOrFail($record->campus_id))) {
                throw new AuthorizationException('Staff campus authorization is no longer active.');
            }
            DB::table('staff_leave_requests')->where('id', $id)
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
            app(SecurityAudit::class)->record('staff.leave.cancelled', $actor, null, $institution, [
                'staff_leave_id' => $id, 'campus_id' => $record->campus_id,
            ]);
        });
    }

    /** Planning figures only. Never generate a payout, bank instruction, tax filing or payslip. */
    public function planPayroll(
        User $actor,
        Institution $institution,
        Campus $campus,
        User $staff,
        string $period,
        string $currency,
        string $gross,
        string $deductions
    ): int {
        if (! $this->canReview($actor, $institution)
            || ! $this->canRequest($staff, $institution, $campus)) {
            throw new AuthorizationException('Payroll planning requires verified leadership and active staff membership.');
        }
        $grossMinor = $this->money($currency, $gross);
        $deductionsMinor = $this->money($currency, $deductions, true);
        if ($deductionsMinor > $grossMinor) {
            throw ValidationException::withMessages(['deductions' => 'Deductions cannot exceed the gross draft amount.']);
        }
        if (! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $period)) {
            throw ValidationException::withMessages(['period' => 'Use YYYY-MM between 2000 and 2099.']);
        }
        return DB::transaction(function () use (
            $actor, $institution, $campus, $staff, $period,
            $currency, $grossMinor, $deductionsMinor
        ): int {
            if (DB::table('payroll_plan_entries')->where('institution_id', $institution->id)
                ->where('staff_user_id', $staff->id)->where('period', $period)->exists()) {
                throw ValidationException::withMessages(['period' => 'A payroll plan already exists for this person and month.']);
            }
            $id = DB::table('payroll_plan_entries')->insertGetId([
                'institution_id' => $institution->id, 'campus_id' => $campus->id,
                'staff_user_id' => $staff->id, 'period' => $period, 'currency' => $currency,
                'gross_minor' => $grossMinor, 'deductions_minor' => $deductionsMinor,
                'net_minor' => $grossMinor - $deductionsMinor,
                'status' => 'draft', 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('payroll.plan.drafted', $actor, null, $institution, [
                'payroll_plan_id' => $id, 'campus_id' => $campus->id,
            ]);
            return $id;
        });
    }

    public function reviewPayroll(User $actor, Institution $institution, int $id, string $decision): void
    {
        if (! $this->canReview($actor, $institution)) {
            throw new AuthorizationException('Payroll planning requires MFA-verified leadership.');
        }
        DB::transaction(function () use ($actor, $institution, $id, $decision): void {
            $record = DB::table('payroll_plan_entries')->where('institution_id', $institution->id)
                ->lockForUpdate()->find($id);
            abort_unless($record, 404);
            if ($record->status !== 'draft' || ! in_array($decision, ['approve', 'void'], true)) {
                throw ValidationException::withMessages(['decision' => 'Only draft payroll plans may be reviewed.']);
            }
            if ((int) $record->created_by === (int) $actor->id) {
                throw new AuthorizationException('Independent school leadership must review this payroll draft.');
            }
            DB::table('payroll_plan_entries')->where('id', $id)->update([
                'status' => $decision === 'approve' ? 'approved' : 'voided',
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            app(SecurityAudit::class)->record('payroll.plan.reviewed', $actor, null, $institution, [
                'payroll_plan_id' => $id, 'campus_id' => $record->campus_id,
            ]);
        });
    }

    private function money(string $currency, string $value, bool $allowZero = false): int
    {
        if (! in_array($currency, ['USD', 'KHR'], true)) {
            throw ValidationException::withMessages(['currency' => 'Only USD and KHR draft planning are supported.']);
        }
        $pattern = $currency === 'KHR'
            ? '/^[0-9]{1,10}$/D'
            : '/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D';
        if (! preg_match($pattern, $value)) {
            throw ValidationException::withMessages(['gross' => 'Enter a valid amount for the selected currency.']);
        }
        $parts = explode('.', $value, 2);
        $minor = $currency === 'KHR'
            ? (int) $parts[0]
            : (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if (! $allowZero && $minor < 1) {
            throw ValidationException::withMessages(['gross' => 'Amount must be positive.']);
        }
        return $minor;
    }
}
