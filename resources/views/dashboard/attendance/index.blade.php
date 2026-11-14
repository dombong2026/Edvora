@extends('layouts.app')
@section('title', 'Attendance · ' . $section->name)
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · {{ $section->campus->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Attendance · {{ $section->name }}</h1>
    <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">Teaching registers require an assigned teacher and independent approval. Families can see only their own approved attendance.</p>
    <a href="{{ route('classrooms.show', [$institution, $section]) }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">Return to class</a>
</header>

@if($canTake)
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Open a teaching register</h2>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">A class may have one register per date ({{ $institution->timezone }}). The register is a draft until submitted and approved.</p>
    <form method="POST" action="{{ route('attendance.store', [$institution, $section]) }}" class="mt-5 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label for="session-date" class="mb-2 block text-sm font-semibold">Class date</label>
            <input id="session-date" name="session_date" type="date" value="{{ old('session_date', today($institution->timezone)->toDateString()) }}" max="{{ today($institution->timezone)->toDateString() }}" required class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('session_date') <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700" type="submit">Open attendance</button>
    </form>
</section>
@endif

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Class registers</h2>
        <span class="text-xs text-slate-500">{{ $sessions->count() }} visible · maximum 100</span>
    </div>
    <ul class="divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($sessions as $session)
            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <div>
                    <p class="text-sm font-semibold">{{ $session->session_date->format('d M Y') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Status: {{ ucfirst($session->status) }}</p>
                </div>
                <a href="{{ route('attendance.show', [$institution, $section, $session]) }}" class="rounded-xl border border-blue-200 px-4 py-2.5 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">View register</a>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-slate-500">No attendance registers are available to your account.</li>
        @endforelse
    </ul>
</section>
@endsection
