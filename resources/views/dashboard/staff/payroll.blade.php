@extends('layouts.app')
@section('title', 'Private payroll planning')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Restricted leadership</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Payroll planning</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">Private, internal payroll scenarios for verified school directors and administrators. Approval is a planning decision only: no payslip, withholding filing, payment order, bank transfer or salary disbursement is produced.</p>
    </div>
    <a href="{{ route('staff.leave.index', $institution) }}" class="edvora-secondary">Leave requests</a>
</header>
<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Create a payroll plan entry</h2>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Only active institution staff members with assigned campus roles are eligible. Enter amounts without currency symbols.</p>
    <form method="POST" action="{{ route('staff.payroll.store', $institution) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @csrf
        <div>
            <label for="plan-campus" class="mb-2 block text-sm font-semibold">Campus</label>
            <select name="campus_id" id="plan-campus" required class="edvora-field">
                <option value="">Choose campus</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('campus_id')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="plan-email" class="mb-2 block text-sm font-semibold">Staff account email</label>
            <input id="plan-email" name="staff_email" type="email" maxlength="255" required value="{{ old('staff_email') }}" class="edvora-field">
            @error('staff_email')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="plan-period" class="mb-2 block text-sm font-semibold">Period (YYYY-MM)</label>
            <input id="plan-period" name="period" type="month" required min="2000-01" max="2099-12" value="{{ old('period', now()->format('Y-m')) }}" class="edvora-field">
            @error('period')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="plan-currency" class="mb-2 block text-sm font-semibold">Currency</label>
            <select id="plan-currency" name="currency" required class="edvora-field">
                <option value="USD" @selected(old('currency', 'USD') === 'USD')>USD</option>
                <option value="KHR" @selected(old('currency') === 'KHR')>KHR</option>
            </select>
        </div>
        <div>
            <label for="plan-gross" class="mb-2 block text-sm font-semibold">Gross amount</label>
            <input id="plan-gross" name="gross" inputmode="decimal" required maxlength="16" value="{{ old('gross') }}" placeholder="1000.00" class="edvora-field">
            @error('gross')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="plan-deductions" class="mb-2 block text-sm font-semibold">Planned deductions</label>
            <input id="plan-deductions" name="deductions" inputmode="decimal" required maxlength="16" value="{{ old('deductions', '0') }}" class="edvora-field">
            @error('deductions')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2 xl:col-span-3 flex justify-end"><button class="edvora-primary" type="submit">Save planning draft</button></div>
    </form>
</section>
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Restricted salary planning records</h2>
        <form action="{{ route('staff.payroll.index', $institution) }}" method="GET" class="flex gap-2">
            <label for="filter-period" class="sr-only">Payroll month</label>
            <input id="filter-period" name="period" type="month" value="{{ $period }}" class="edvora-field">
            <button type="submit" class="edvora-secondary">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[790px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-5 py-3">Staff / period</th><th class="px-5 py-3">Gross</th><th class="px-5 py-3">Deductions</th><th class="px-5 py-3">Net plan</th><th class="px-5 py-3">Status / action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($plans as $plan)
                @php
                    $unit = $plan->currency === 'USD' ? 100 : 1;
                    $symbol = $plan->currency === 'USD' ? '$' : 'KHR ';
                    $decimals = $plan->currency === 'USD' ? 2 : 0;
                @endphp
                <tr>
                    <td class="px-5 py-4"><p class="font-semibold">{{ $plan->staff_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $plan->period }}</p></td>
                    <td class="px-5 py-4 tabular-nums">{{ $symbol }}{{ number_format($plan->gross_minor / $unit, $decimals) }}</td>
                    <td class="px-5 py-4 tabular-nums">{{ $symbol }}{{ number_format($plan->deductions_minor / $unit, $decimals) }}</td>
                    <td class="px-5 py-4 font-bold tabular-nums">{{ $symbol }}{{ number_format($plan->net_minor / $unit, $decimals) }}</td>
                    <td class="px-5 py-4">
                        <p class="text-xs font-bold">{{ ucfirst($plan->status) }}</p>
                        @if($plan->status === 'draft' && (int)$plan->created_by !== (int)auth()->id())
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach(['approve'=>'Approve plan','void'=>'Void'] as $decision => $label)
                                <form method="POST" action="{{ route('staff.payroll.review', [$institution, $plan->id]) }}">
                                    @csrf<input type="hidden" name="decision" value="{{ $decision }}">
                                    <button type="submit" class="{{ $decision === 'approve' ? 'edvora-primary' : 'edvora-secondary' }}">{{ $label }}</button>
                                </form>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No payroll planning records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($plans->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $plans->links() }}</div>@endif
</section>
@endsection
