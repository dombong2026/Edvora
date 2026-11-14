@extends('layouts.app')
@section('title', 'Academic reports')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Leadership reporting</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Academic reports</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">Real aggregate academic counts for an authorized school or campus. No individual grades, names, guardian identities or payment information appear here.</p>
    </div>
    <a href="{{ route('dashboard.institution', $institution) }}" class="edvora-secondary">Return to dashboard</a>
</header>
<section class="edvora-surface p-6">
    <form action="{{ route('reports.index', $institution) }}" method="GET" class="flex flex-wrap items-end gap-4">
        <div class="min-w-60 flex-1">
            <label for="report-campus" class="mb-2 block text-sm font-semibold">Reporting scope</label>
            <select id="report-campus" name="campus_id" class="edvora-field">
                <option value="">Entire authorized institution</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected($selectedCampus?->id === $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="edvora-primary">Apply scope</button>
        <a class="edvora-secondary" href="{{ route('reports.export', ['institution' => $institution] + ($selectedCampus ? ['campus_id' => $selectedCampus->id] : [])) }}">Download summary CSV</a>
    </form>
    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Generated from current database records. Counts for academic years and subjects are institution-level, even when a campus is selected.</p>
</section>
<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($labels as $key => $label)
        <article class="edvora-surface p-6">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-4 text-4xl font-extrabold tracking-tight tabular-nums">{{ number_format($metrics[$key]) }}</p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $selectedCampus?->name ?? $institution->name }}</p>
        </article>
    @endforeach
</div>
@endsection
