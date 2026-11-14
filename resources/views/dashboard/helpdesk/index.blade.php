@extends('layouts.app')
@section('title', 'Institution support')
@section('content')
<header class="mb-7 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Support center</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Private support requests</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">Request help from your school. Only you and verified institution leadership can read your support thread. No external emails are sent.</p>
    </div>
    <a href="{{ route('dashboard.institution', $institution) }}" class="edvora-secondary">Back to dashboard</a>
</header>
<section class="edvora-surface p-6" aria-labelledby="new-ticket-heading">
    <h2 id="new-ticket-heading" class="text-lg font-bold">Open a support request</h2>
    @if($campuses->isEmpty())
        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">An active campus assignment is needed before opening a request.</p>
    @else
        <form action="{{ route('helpdesk.store', $institution) }}" method="POST" class="mt-5 grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label for="help-campus" class="mb-2 block text-sm font-semibold">Campus</label>
                <select id="help-campus" name="campus_id" required class="edvora-field">
                    <option value="">Choose your campus</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
                @error('campus_id')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="help-category" class="mb-2 block text-sm font-semibold">Category</label>
                <select id="help-category" name="category" required class="edvora-field">
                    <option value="">Choose a category</option>
                    @foreach($categories as $key => $caption)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $caption }}</option>
                    @endforeach
                </select>
                @error('category')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="help-title" class="mb-2 block text-sm font-semibold">Subject</label>
                <input id="help-title" name="title" required minlength="5" maxlength="160" value="{{ old('title') }}" class="edvora-field" placeholder="Describe the question briefly">
                @error('title')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="help-body" class="mb-2 block text-sm font-semibold">How can your school help?</label>
                <textarea id="help-body" name="body" required minlength="10" maxlength="4000" rows="5" class="edvora-field" placeholder="Please describe the issue, without including passwords, ID numbers or payment details.">{{ old('body') }}</textarea>
                @error('body')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">Internal record only; this form does not notify external services.</p>
                <button type="submit" class="edvora-primary">Submit support request</button>
            </div>
        </form>
    @endif
</section>
<section class="edvora-surface mt-6 overflow-hidden" aria-labelledby="existing-tickets-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 id="existing-tickets-heading" class="text-lg font-bold">{{ $officer ? 'Institution requests' : 'My support requests' }}</h2>
        <form action="{{ route('helpdesk.index', $institution) }}" method="GET" class="flex flex-wrap gap-2">
            <label for="ticket-query" class="sr-only">Search subject</label>
            <input id="ticket-query" type="search" name="q" maxlength="100" class="edvora-field w-48" value="{{ $search }}" placeholder="Search subjects">
            <label for="ticket-status" class="sr-only">Filter by status</label>
            <select id="ticket-status" name="status" class="edvora-field w-32">
                <option value="">All statuses</option>
                @foreach(['open', 'answered', 'closed'] as $option)
                    <option value="{{ $option }}" @selected($statusFilter === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <button type="submit" class="edvora-primary">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[570px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-6 py-3">Subject</th><th class="px-6 py-3">Category</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Last activity</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="px-6 py-4"><a class="font-bold text-blue-600 hover:underline dark:text-blue-400" href="{{ route('helpdesk.show', [$institution, $ticket->id]) }}">{{ $ticket->title }}</a></td>
                        <td class="px-6 py-4">{{ $categories[$ticket->category] ?? ucfirst($ticket->category) }}</td>
                        <td class="px-6 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold dark:bg-slate-800">{{ ucfirst($ticket->status) }}</span></td>
                        <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($ticket->last_activity_at)->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500 dark:text-slate-400">No matching requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tickets->hasPages())
        <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $tickets->links() }}</div>
    @endif
</section>
@endsection
