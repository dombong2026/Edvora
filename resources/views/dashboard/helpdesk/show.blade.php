@extends('layouts.app')
@section('title', $ticket->title)
@section('content')
<div class="mb-7 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Private support</p>
        <h1 class="mt-2 max-w-3xl text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $ticket->title }}</h1>
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Ticket #{{ $ticket->id }} · {{ ucfirst($ticket->category) }} · {{ ucfirst($ticket->status) }}</p>
    </div>
    <a href="{{ route('helpdesk.index', $institution) }}" class="edvora-secondary">All my requests</a>
</div>
<section class="edvora-surface overflow-hidden" aria-labelledby="conversation-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 id="conversation-heading" class="text-lg font-bold">Conversation</h2>
        <form method="POST" action="{{ route('helpdesk.status', [$institution, $ticket->id]) }}">
            @csrf
            <input type="hidden" name="action" value="{{ $ticket->status === 'closed' ? 'reopen' : 'close' }}">
            <button type="submit" class="edvora-secondary">{{ $ticket->status === 'closed' ? 'Reopen ticket' : 'Close ticket' }}</button>
        </form>
    </div>
    <ol class="divide-y divide-slate-100 dark:divide-slate-800">
        @foreach($replies as $reply)
            <li class="px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-bold">{{ $reply->name }} @if((int) $reply->author_user_id === (int) $ticket->created_by)<span class="ml-1 text-xs font-normal text-blue-600 dark:text-blue-400">Request author</span>@endif</p>
                    <time class="text-xs text-slate-500 dark:text-slate-400" datetime="{{ $reply->created_at }}">{{ \Carbon\Carbon::parse($reply->created_at)->format('d M Y H:i') }}</time>
                </div>
                <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700 dark:text-slate-200">{{ $reply->body }}</p>
            </li>
        @endforeach
    </ol>
</section>
@if($ticket->status !== 'closed')
<section class="edvora-surface mt-6 p-6" aria-labelledby="reply-heading">
    <h2 id="reply-heading" class="text-lg font-bold">Write a private reply</h2>
    <form action="{{ route('helpdesk.reply', [$institution, $ticket->id]) }}" method="POST" class="mt-5">
        @csrf
        <label for="reply-body" class="mb-2 block text-sm font-semibold">Message</label>
        <textarea id="reply-body" name="body" rows="5" required minlength="2" maxlength="4000" class="edvora-field" placeholder="Do not include passwords, verification codes or other sensitive secrets.">{{ old('body') }}</textarea>
        @error('body')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        <div class="mt-4 flex justify-end"><button type="submit" class="edvora-primary">Post reply</button></div>
    </form>
</section>
@else
    <p class="mt-5 text-sm text-slate-500 dark:text-slate-400">This request is closed. Reopen it to add another message.</p>
@endif
@endsection
