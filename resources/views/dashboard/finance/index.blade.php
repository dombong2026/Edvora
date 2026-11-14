@extends('layouts.app')
@section('title', 'School billing & internal reconciliation')
@section('content')
<header class="mb-7 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Authorized finance</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Billing & manual reconciliation</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">A restricted internal register for draft/issued school invoices and bank-statement matching requests. No card payments, KHQR collection, automatic bank verification, receipt delivery or external billing dispatch is enabled.</p>
    </div>
    <a href="{{ route('dashboard.institution', $institution) }}" class="edvora-secondary">Institution dashboard</a>
</header>
<div class="mb-6 flex flex-wrap gap-2">
    <a href="{{ route('workspaces.index', [$institution, 'cashier']) }}" class="edvora-secondary">Fee proposal review</a>
    @if($canReview)
        <a href="{{ route('staff.payroll.index', $institution) }}" class="edvora-secondary">Payroll planning</a>
    @endif
</div>
<section class="edvora-surface p-6">
    <form method="GET" action="{{ route('finance.index', $institution) }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <label for="finance-campus" class="mb-2 block text-sm font-semibold">Authorized campus</label>
            <select id="finance-campus" name="campus_id" class="edvora-field" required>
                @foreach($campuses as $option)
                    <option value="{{ $option->id }}" @selected($campus->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="edvora-primary">View campus</button>
    </form>
</section>
<section class="edvora-surface mt-6 p-6">
    <h2 class="text-lg font-bold">Create a school invoice draft</h2>
    <p class="mt-2 text-sm leading-7 text-slate-500 dark:text-slate-400">Choose an active student and an independently reviewed fee proposal from this campus. The invoice amount and currency are copied directly from that proposal.</p>
    @if($reviewedProposals->isNotEmpty() && $students->isNotEmpty())
    <form action="{{ route('finance.invoices.store', $institution) }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
        <div>
            <label for="invoice-number" class="mb-2 block text-sm font-semibold">Unique invoice number</label>
            <input id="invoice-number" name="invoice_number" required maxlength="40" pattern="[A-Za-z0-9_-]{3,40}" value="{{ old('invoice_number') }}" class="edvora-field" placeholder="INV-2026-00001">
            @error('invoice_number')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="invoice-student" class="mb-2 block text-sm font-semibold">Student</label>
            <select id="invoice-student" name="student_profile_id" required class="edvora-field">
                <option value="">Choose verified student</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected((string)old('student_profile_id') === (string)$student->id)>{{ $student->student_number }} · {{ $student->display_name }}</option>
                @endforeach
            </select>
            @error('student_profile_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="invoice-fee" class="mb-2 block text-sm font-semibold">Reviewed fee proposal</label>
            <select id="invoice-fee" name="fee_proposal_id" required class="edvora-field">
                <option value="">Choose fee proposal</option>
                @foreach($reviewedProposals as $fee)
                    <option value="{{ $fee->id }}" @selected((string)old('fee_proposal_id') === (string)$fee->id)>{{ $fee->name }} · {{ $fee->currency }} {{ number_format($fee->amount_minor / ($fee->currency === 'USD' ? 100 : 1), $fee->currency === 'USD' ? 2 : 0) }}</option>
                @endforeach
            </select>
            @error('fee_proposal_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end"><button type="submit" class="edvora-primary w-full">Create draft invoice</button></div>
    </form>
    @else
        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">An active student and a reviewed campus fee proposal are required before invoicing. Use Fee proposal review to prepare the amount.</p>
    @endif
</section>
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Invoices and matched amounts</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The matched amount represents independently reviewed manual entries, not a verified bank balance.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[890px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-5 py-3">Invoice / student</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Verified manual matches</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($invoices as $invoice)
                @php
                    $unit = $invoice->currency === 'USD' ? 100 : 1;
                    $digits = $invoice->currency === 'USD' ? 2 : 0;
                    $matched = (int) ($verifiedTotals[$invoice->id] ?? 0);
                @endphp
                <tr>
                    <td class="px-5 py-4"><p class="font-bold">{{ $invoice->invoice_number }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $invoice->student_number }} · {{ $invoice->student_name }}</p></td>
                    <td class="px-5 py-4 tabular-nums">{{ $invoice->currency }} {{ number_format($invoice->amount_minor / $unit, $digits) }}</td>
                    <td class="px-5 py-4 tabular-nums">{{ $invoice->currency }} {{ number_format($matched / $unit, $digits) }}</td>
                    <td class="px-5 py-4 font-semibold">{{ ucfirst($invoice->status) }}</td>
                    <td class="px-5 py-4">
                        @if($invoice->status === 'draft')
                            <div class="flex flex-wrap gap-2">
                                @if($canReview && (int)$invoice->created_by !== (int)auth()->id())
                                    <form method="POST" action="{{ route('finance.invoices.review', [$institution, $invoice->id]) }}">
                                        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="decision" value="issue">
                                        <button type="submit" class="edvora-primary">Issue internally</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('finance.invoices.review', [$institution, $invoice->id]) }}">
                                    @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="decision" value="void">
                                    <button type="submit" class="edvora-secondary">Void draft</button>
                                </form>
                            </div>
                        @elseif($invoice->status === 'issued' && $matched < (int)$invoice->amount_minor)
                            <details>
                                <summary class="cursor-pointer text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Record bank-statement match</summary>
                                <form method="POST" action="{{ route('finance.matches.store', [$institution, $invoice->id]) }}" class="mt-3 grid gap-2">
                                    @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
                                    <label for="statement-{{ $invoice->id }}" class="text-xs font-semibold">Statement reference</label>
                                    <input id="statement-{{ $invoice->id }}" name="statement_reference" maxlength="80" required pattern="[A-Za-z0-9_-]{4,80}" class="edvora-field">
                                    <label for="match-amount-{{ $invoice->id }}" class="text-xs font-semibold">Amount in {{ $invoice->currency }}</label>
                                    <input id="match-amount-{{ $invoice->id }}" name="amount" maxlength="16" required inputmode="decimal" class="edvora-field">
                                    <button type="submit" class="edvora-primary">Submit for review</button>
                                </form>
                            </details>
                        @else
                            <span class="text-xs text-slate-500 dark:text-slate-400">No available action</span>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-9 text-center text-slate-500">No invoices exist in this campus.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $invoices->links() }}</div>@endif
</section>
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Manual reconciliation register</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Recent 30 entries; records are not bank-confirmed receipts.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[770px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-5 py-3">Invoice</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Review status</th><th class="px-5 py-3">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($matches as $match)
                <tr>
                    <td class="px-5 py-4 font-semibold">{{ $match->invoice_number }}</td>
                    <td class="px-5 py-4 font-mono text-xs">{{ $match->statement_reference }}</td>
                    <td class="px-5 py-4 tabular-nums">{{ $match->currency }} {{ number_format($match->amount_minor / ($match->currency === 'USD' ? 100 : 1), $match->currency === 'USD' ? 2 : 0) }}</td>
                    <td class="px-5 py-4">{{ ucfirst($match->status) }}</td>
                    <td class="px-5 py-4">
                        @if($canReview && $match->status === 'pending' && (int)$match->recorded_by !== (int)auth()->id())
                            <div class="flex flex-wrap gap-2">
                                @foreach(['verify'=>'Verify manual match','reject'=>'Reject claim'] as $decision => $caption)
                                    <form method="POST" action="{{ route('finance.matches.review', [$institution, $match->id]) }}">
                                        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="decision" value="{{ $decision }}">
                                        <button type="submit" class="{{ $decision === 'verify' ? 'edvora-primary' : 'edvora-secondary' }}">{{ $caption }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-slate-500">Review complete or pending another officer</span>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-9 text-center text-slate-500">No manual reconciliation entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
