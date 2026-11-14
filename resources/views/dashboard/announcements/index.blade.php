@extends('layouts.app')
@section('title', 'School announcements')
@section('content')
<header class="mb-7 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · News & updates</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">School announcements</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">Official school notices approved for your institution or campus. Drafts require independent school leadership review.</p>
    </div>
    <a href="{{ route('dashboard.institution', $institution) }}" class="edvora-secondary">Back to dashboard</a>
</header>
@if($canManage)
<section class="edvora-surface mb-6 p-6" aria-labelledby="new-notice-heading">
    <h2 id="new-notice-heading" class="text-lg font-bold">Draft a school announcement</h2>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">A different authorized director or administrator must publish the draft. Drafts are never shown to non-officers.</p>
    <form method="POST" action="{{ route('announcements.store', $institution) }}" class="mt-5 grid gap-4 md:grid-cols-2">
        @csrf
        <div>
            <label for="notice-campus" class="mb-2 block text-sm font-semibold">Audience</label>
            <select id="notice-campus" name="campus_id" class="edvora-field">
                <option value="">All authorized institution members</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }} only</option>
                @endforeach
            </select>
            @error('campus_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="notice-title" class="mb-2 block text-sm font-semibold">Title</label>
            <input id="notice-title" name="title" required minlength="5" maxlength="160" value="{{ old('title') }}" class="edvora-field">
            @error('title')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="notice-body" class="mb-2 block text-sm font-semibold">Message</label>
            <textarea id="notice-body" name="body" required minlength="10" maxlength="5000" rows="5" class="edvora-field" placeholder="Write a clear, factual update. Avoid confidential student records.">{{ old('body') }}</textarea>
            @error('body')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2 flex justify-end"><button type="submit" class="edvora-primary">Save as draft</button></div>
    </form>
</section>
@endif
<section class="grid gap-4" aria-label="Available announcements">
    @forelse($announcements as $announcement)
        <article class="edvora-surface p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ ucfirst($announcement->status) }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $announcement->published_at ? 'Published '.\Carbon\Carbon::parse($announcement->published_at)->format('d M Y') : 'Not published' }}</span>
            </div>
            <h2 class="mt-4 text-lg font-bold">{{ $announcement->title }}</h2>
            <p class="mt-2 line-clamp-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $announcement->body }}</p>
            <a class="mt-4 inline-flex text-sm font-bold text-blue-600 hover:underline dark:text-blue-400" href="{{ route('announcements.show', [$institution, $announcement->id]) }}">Read announcement →</a>
        </article>
    @empty
        <div class="edvora-surface p-8 text-sm text-slate-500 dark:text-slate-400">No announcements are available to this account.</div>
    @endforelse
</section>
@if($announcements->hasPages())
    <div class="mt-5">{{ $announcements->links() }}</div>
@endif
@endsection
