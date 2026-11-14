@extends('layouts.app')
@section('title', 'My notifications')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Private in-app messages</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">My notifications</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">School-record and staff leave decisions are displayed here only for your account. External emails, SMS and push notifications are not sent.</p>
    </div>
    <a href="{{ route('dashboard.institution', $institution) }}" class="edvora-secondary">Institution dashboard</a>
</header>
<section class="edvora-surface overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Inbox · {{ $unreadCount }} unread</h2>
        <form method="GET" action="{{ route('inbox.index', $institution) }}" class="flex items-center gap-2">
            <label for="inbox-filter" class="sr-only">Notification filter</label>
            <select id="inbox-filter" name="filter" class="edvora-field">
                <option value="all" @selected(!$unreadOnly)>All notifications</option>
                <option value="unread" @selected($unreadOnly)>Unread only</option>
            </select>
            <button type="submit" class="edvora-secondary">Apply filter</button>
        </form>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($notifications as $notification)
            <article class="flex flex-wrap items-start justify-between gap-4 px-6 py-5">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold">{{ $notification->title }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($notification->created_at)->format('d M Y, H:i') }} · {{ $notification->read_at ? 'Read' : 'Unread' }}</p>
                    <a href="{{ $notification->path }}" class="mt-3 inline-flex text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">Open the related school workspace</a>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if(!$notification->read_at)
                    <form method="POST" action="{{ route('inbox.mark', [$institution, $notification->id]) }}">
                        @csrf<input type="hidden" name="action" value="read">
                        <button type="submit" class="edvora-primary">Mark as read</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('inbox.mark', [$institution, $notification->id]) }}">
                        @csrf<input type="hidden" name="action" value="archive">
                        <button type="submit" class="edvora-secondary">Archive</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No matching notifications.</p>
        @endforelse
    </div>
    @if($notifications->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $notifications->links() }}</div>@endif
</section>
@endsection
