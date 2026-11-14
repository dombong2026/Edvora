@extends('layouts.app')
@section('title', 'School-reviewed academic statements')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Private academic records</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Academic results statements</h1>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $profile->display_name }} · {{ $profile->student_number }}</p>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500 dark:text-slate-400">Ask the institution to issue an immutable record of independently published results for a specific academic year. These records are not yet legally certified transcripts. Each issuance requires separate school leadership review.</p>
    </div>
    <a href="{{ route('students.transcript.show', [$institution, $profile->id]) }}" class="edvora-secondary">Reviewed results</a>
</header>
@if($years->isNotEmpty())
<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Request a statement</h2>
    <form method="POST" action="{{ route('students.statements.store', [$institution, $profile->id]) }}" class="mt-4 flex flex-wrap items-end gap-3">
        @csrf
        <div class="min-w-60 flex-1">
            <label class="mb-2 block text-sm font-semibold" for="statement-year">Academic year</label>
            <select name="academic_year_id" id="statement-year" class="edvora-field" required>
                <option value="">Select academic year</option>
                @foreach($years as $year)
                    <option value="{{ $year->id }}" @selected((string)old('academic_year_id') === (string)$year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
            @error('academic_year_id')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="edvora-primary">Request independent review</button>
    </form>
</section>
@else
<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">No linked academic years</h2>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">A verified class enrollment and published academic results are required before a statement can be issued.</p>
</section>
@endif
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Statement request history</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Statement snapshots are only available while issued and in good standing. Revoked statements remain on record but cannot be downloaded.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-5 py-3">Academic year</th><th class="px-5 py-3">Record</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Requested</th><th class="px-5 py-3">Available actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($history as $item)
                <tr>
                    <td class="px-5 py-4 font-semibold">{{ $item->year_name }}</td>
                    <td class="px-5 py-4 font-mono text-xs">{{ $item->statement_number ?? 'Unissued' }}</td>
                    <td class="px-5 py-4">
                        <p class="font-semibold">{{ ucfirst($item->status) }}</p>
                        @if($item->status === 'revoked')<p class="mt-1 max-w-sm text-xs text-slate-500 dark:text-slate-400">Revoked by the institution</p>@endif
                    </td>
                    <td class="px-5 py-4 text-xs">{{ \Carbon\Carbon::parse($item->created_at)->format('d M Y') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-2">
                            @if($item->status === 'issued')
                                <a href="{{ route('students.statements.show', [$institution, $profile->id, $item->id]) }}" class="edvora-primary">View statement</a>
                                <a href="{{ route('students.statements.export', [$institution, $profile->id, $item->id]) }}" class="edvora-secondary">Download CSV</a>
                            @elseif($item->status === 'pending' && $canReview && (int)$item->requested_by !== (int)auth()->id())
                                @foreach(['issue' => 'Issue snapshot', 'reject' => 'Reject'] as $decision => $caption)
                                <form method="POST" action="{{ route('students.statements.review', [$institution, $profile->id, $item->id]) }}">
                                    @csrf<input type="hidden" name="decision" value="{{ $decision }}">
                                    <button type="submit" class="{{ $decision === 'issue' ? 'edvora-primary' : 'edvora-secondary' }}">{{ $caption }}</button>
                                </form>
                                @endforeach
                            @else
                                <span class="text-xs text-slate-500 dark:text-slate-400">No available action</span>
                            @endif
                            @if($canReview && $item->status === 'issued' && (int)$item->reviewed_by !== (int)auth()->id())
                                <details class="max-w-xs">
                                    <summary class="cursor-pointer text-xs font-semibold text-red-600 dark:text-red-400">Revoke</summary>
                                    <form method="POST" action="{{ route('students.statements.revoke', [$institution, $profile->id, $item->id]) }}" class="mt-2 flex flex-col gap-2">
                                        @csrf
                                        <label for="revocation-reason-{{ $item->id }}" class="text-xs font-semibold">Reason for revocation</label>
                                        <textarea id="revocation-reason-{{ $item->id }}" name="reason" required minlength="10" maxlength="255" rows="2" class="edvora-field"></textarea>
                                        <button type="submit" class="edvora-secondary">Confirm revocation</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No statement requests exist for this student.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($history->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $history->links() }}</div>@endif
</section>
@endsection
