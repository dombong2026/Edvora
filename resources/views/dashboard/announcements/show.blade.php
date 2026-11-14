@extends('layouts.app')
@section('title', $announcement->title)
@section('content')
<header class="mb-7">
    <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · School announcements</p>
    <h1 class="mt-2 max-w-3xl text-3xl font-extrabold tracking-tight">{{ $announcement->title }}</h1>
    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ ucfirst($announcement->status) }} · {{ $announcement->published_at ? 'Published '.\Carbon\Carbon::parse($announcement->published_at)->format('d M Y H:i') : 'Awaiting review' }}</p>
    <a href="{{ route('announcements.index', $institution) }}" class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">Back to announcements</a>
</header>
<article class="edvora-surface p-6 sm:p-8">
    <p class="whitespace-pre-wrap break-words text-base leading-8 text-slate-700 dark:text-slate-200">{{ $announcement->body }}</p>
</article>
@if($canManage)
<section class="edvora-surface mt-6 p-6" aria-label="School leadership review">
    <h2 class="text-lg font-bold">Publication controls</h2>
    <p class="mt-2 text-sm leading-7 text-slate-500 dark:text-slate-400">Publication requires a separate director or administrator. Archived notices cannot be republished in this workflow.</p>
    @if($announcement->status === 'draft' && (int) $announcement->created_by !== (int) auth()->id())
        <form action="{{ route('announcements.transition', [$institution, $announcement->id]) }}" method="POST" class="mt-5">
            @csrf
            <input type="hidden" name="action" value="publish">
            <button type="submit" class="edvora-primary">Approve and publish</button>
        </form>
    @elseif($announcement->status === 'published')
        <form action="{{ route('announcements.transition', [$institution, $announcement->id]) }}" method="POST" class="mt-5">
            @csrf
            <input type="hidden" name="action" value="archive">
            <button type="submit" class="edvora-secondary">Archive notice</button>
        </form>
    @else
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->status === 'draft' ? 'Waiting for another authorized officer to publish.' : 'No further action is available.' }}</p>
    @endif
</section>
@endif
@endsection
