@extends('layouts.app')
@section('title', $institution->name . ' dashboard')
@section('content')
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Institution workspace</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $institution->name }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Academic administration overview · {{ $selectedCampus?->name ?? 'Authorized campuses' }}</p>
    </div>
    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">Active institution</span>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Campuses you can access</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ $campuses->count() }}</p>
        <p class="mt-2 text-xs text-slate-500">Scoped to your assigned permissions</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Active institution members</p>
        @if(in_array('administrator', $roleCodes, true) || in_array('director', $roleCodes, true))
            <p class="mt-4 text-4xl font-bold tracking-tight">{{ $memberCount }}</p>
        @else
            <p class="mt-4 text-xl font-bold">Restricted</p>
        @endif
        <p class="mt-2 text-xs text-slate-500">Visible to authorized institution leadership only</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Assigned account roles</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ count($roleCodes) }}</p>
        <p class="mt-2 text-xs text-slate-500">Granted by your institution</p>
    </article>
</div>

<div class="mt-6 grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold">My campuses</h2>
            <span class="text-xs text-slate-500">{{ $campuses->count() }} available</span>
        </div>
        <div class="mt-5 space-y-2">
            @forelse($campuses as $campus)
                <a class="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold hover:border-blue-300 hover:bg-blue-50 dark:border-slate-800 dark:hover:border-blue-900 dark:hover:bg-blue-950" href="{{ route('dashboard.campus', [$institution, $campus]) }}">
                    <span>{{ $campus->name }}</span>
                    <span aria-hidden="true" class="text-blue-600">→</span>
                </a>
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">No active campus assignments are available for this account.</p>
            @endforelse
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-bold">{{ $selectedRole ? $selectedRole->label() . ' workspace' : 'My account responsibilities' }}</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Your assigned roles determine which Edvora modules and records become available. Academic, financial, and personnel modules will be connected after their independent authorization tests are complete.</p>
        <div class="mt-5 flex flex-wrap gap-2">
            @foreach($roleCodes as $code)
                <a href="{{ route('dashboard.role', [$institution, $code]) }}" class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">{{ $code === 'subdirector' ? 'Deputy Director' : ucfirst($code) }}</a>
            @endforeach
        </div>
    </section>
</div>
@endsection
