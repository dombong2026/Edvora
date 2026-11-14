@extends('layouts.app')
@section('title', 'Staff leave requests')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Staff management</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Leave requests</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">Submit a real, campus-scoped leave request or review pending team requests where assigned. Leave category and dates are stored privately. This workspace does not adjust payroll.</p>
    </div>
    <a href="{{ route('dashboard.institution', $institution) }}" class="edvora-secondary">Institution dashboard</a>
</header>
@if($campuses->isNotEmpty())
<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Request leave</h2>
    <form action="{{ route('staff.leave.store', $institution) }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
            <label for="leave-campus" class="mb-2 block text-sm font-semibold">Assigned campus</label>
            <select id="leave-campus" name="campus_id" class="edvora-field" required>
                <option value="">Choose campus</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string)old('campus_id') === (string)$campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('campus_id')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="leave-type" class="mb-2 block text-sm font-semibold">Category</label>
            <select id="leave-type" name="category" class="edvora-field" required>
                <option value="annual" @selected(old('category', 'annual') === 'annual')>Annual</option>
                <option value="personal" @selected(old('category') === 'personal')>Personal</option>
                <option value="other" @selected(old('category') === 'other')>Other</option>
            </select>
            @error('category')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="leave-start" class="mb-2 block text-sm font-semibold">From date</label>
            <input id="leave-start" name="starts_on" type="date" required min="{{ now()->toDateString() }}" value="{{ old('starts_on') }}" class="edvora-field">
            @error('starts_on')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="leave-end" class="mb-2 block text-sm font-semibold">To date</label>
            <input id="leave-end" name="ends_on" type="date" required min="{{ now()->toDateString() }}" value="{{ old('ends_on') }}" class="edvora-field">
            @error('ends_on')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2 xl:col-span-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500 dark:text-slate-400">Maximum 31 calendar days per request; overlapping requests are blocked.</p>
            <button type="submit" class="edvora-primary">Submit leave request</button>
        </div>
    </form>
</section>
@endif
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <div>
            <h2 class="text-lg font-bold">{{ $canReview ? 'Institution staff requests' : 'My leave requests' }}</h2>
            @if($canReview)<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Independent school leadership review is required.</p>@endif
        </div>
        <form method="GET" action="{{ route('staff.leave.index', $institution) }}" class="flex items-center gap-2">
            <label for="leave-status" class="sr-only">Status</label>
            <select id="leave-status" name="status" class="edvora-field">
                <option value="">All statuses</option>
                @foreach(['pending', 'approved', 'rejected', 'cancelled'] as $value)
                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <button type="submit" class="edvora-secondary">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[690px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-5 py-3">Staff</th><th class="px-5 py-3">Category</th><th class="px-5 py-3">Dates</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($requests as $leave)
                <tr>
                    <td class="px-5 py-4 font-semibold">{{ $leave->staff_name }}</td>
                    <td class="px-5 py-4">{{ ucfirst($leave->category) }}</td>
                    <td class="px-5 py-4 text-xs">{{ \Carbon\Carbon::parse($leave->starts_on)->format('d M Y') }} – {{ \Carbon\Carbon::parse($leave->ends_on)->format('d M Y') }}</td>
                    <td class="px-5 py-4">{{ ucfirst($leave->status) }}</td>
                    <td class="px-5 py-4">
                        @if($leave->status === 'pending')
                            <div class="flex flex-wrap gap-2">
                                @if($canReview && (int)$leave->staff_user_id !== (int)auth()->id())
                                    @foreach(['approve'=>'Approve','reject'=>'Reject'] as $decision => $label)
                                        <form method="POST" action="{{ route('staff.leave.review', [$institution, $leave->id]) }}">
                                            @csrf<input type="hidden" name="decision" value="{{ $decision }}">
                                            <button class="{{ $decision === 'approve' ? 'edvora-primary' : 'edvora-secondary' }}" type="submit">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                @endif
                                @if((int)$leave->staff_user_id === (int)auth()->id())
                                    <form method="POST" action="{{ route('staff.leave.cancel', [$institution, $leave->id]) }}">
                                        @csrf<button type="submit" class="edvora-secondary">Cancel request</button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-slate-500">Reviewed</span>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-9 text-center text-slate-500">No leave requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $requests->links() }}</div>@endif
</section>
@endsection
