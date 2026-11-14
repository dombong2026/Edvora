@extends('layouts.app')
@section('title', 'School Management & Education ERP')
@section('content')
<section class="relative isolate overflow-hidden rounded-[2rem] border border-blue-100 bg-gradient-to-br from-white via-blue-50 to-indigo-100 px-6 py-12 shadow-sm sm:px-10 sm:py-20 xl:px-16 dark:border-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950">
    <svg class="pointer-events-none absolute -right-20 -top-24 -z-10 h-[27rem] w-[27rem] text-blue-200/50 dark:text-blue-800/30" viewBox="0 0 400 400" fill="none" aria-hidden="true">
        <circle cx="200" cy="200" r="185" stroke="currentColor" stroke-width="2"/><circle cx="200" cy="200" r="145" stroke="currentColor" stroke-width="2"/><circle cx="200" cy="200" r="105" stroke="currentColor" stroke-width="2"/><circle cx="200" cy="200" r="65" stroke="currentColor" stroke-width="2"/>
    </svg>
    <div class="max-w-4xl">
        <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/85 px-4 py-2 text-xs font-bold text-blue-700 shadow-sm dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">
            <span class="h-2 w-2 rounded-full bg-blue-500" aria-hidden="true"></span> Education management · Edvora v{{ config('edvora.version') }}
        </span>
        <h1 class="mt-7 max-w-4xl text-4xl font-extrabold leading-[1.12] tracking-tight sm:text-6xl">A smarter home for <span class="text-blue-600 dark:text-blue-400">school operations.</span></h1>
        <p class="mt-7 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg dark:text-slate-300">One secure, thoughtfully designed workspace connecting institution teams, teaching, student records and academic workflows — with permissions that protect every school community.</p>
        <div class="mt-9 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="edvora-primary px-6 py-3">Open my dashboard <span aria-hidden="true">→</span></a>
                <a href="{{ route('account.index') }}" class="edvora-secondary px-6 py-3">My account</a>
            @else
                <a href="{{ route('login') }}" class="edvora-primary px-6 py-3">Sign in to Edvora <span aria-hidden="true">→</span></a>
                @if(config('edvora.self_registration'))
                    <a href="{{ route('register') }}" class="edvora-secondary px-6 py-3">Create an account</a>
                @else
                    <a href="#capabilities" class="edvora-secondary px-6 py-3">Explore the foundation</a>
                @endif
            @endauth
        </div>
        <div class="mt-10 flex flex-wrap gap-x-7 gap-y-3 border-t border-blue-200/80 pt-6 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">
            <span class="flex items-center gap-2"><span class="text-blue-600 dark:text-blue-400" aria-hidden="true">✓</span> Institution-scoped access</span>
            <span class="flex items-center gap-2"><span class="text-blue-600 dark:text-blue-400" aria-hidden="true">✓</span> Authenticator security</span>
            <span class="flex items-center gap-2"><span class="text-blue-600 dark:text-blue-400" aria-hidden="true">✓</span> Responsive light and dark modes</span>
        </div>
    </div>
</section>
<section id="capabilities" class="py-12 sm:py-16" aria-labelledby="capabilities-heading">
    <div class="mb-7 max-w-2xl">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Purpose-built foundation</p>
        <h2 id="capabilities-heading" class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">A consistent experience for every role</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Available functions follow each member's assigned institution, campus and account permissions. School-specific actions require a verified account.</p>
    </div>
    <div class="grid gap-5 md:grid-cols-3">
        <article class="edvora-surface p-6">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300"><x-nav-icon name="security" class="h-6 w-6" /></span>
            <h3 class="mt-5 text-lg font-bold">Identity & access</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Institution invitations, campus membership, assigned roles and secure authenticator-based sign-in.</p>
        </article>
        <article class="edvora-surface p-6">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300"><x-nav-icon name="classrooms" class="h-6 w-6" /></span>
            <h3 class="mt-5 text-lg font-bold">Classrooms & planning</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Academic years, subjects, class assignments, authorized enrollments and managed timetables.</p>
        </article>
        <article class="edvora-surface p-6">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300"><x-nav-icon name="academic" class="h-6 w-6" /></span>
            <h3 class="mt-5 text-lg font-bold">Academic records</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Controlled attendance, assessments and approval-based corrections with audit history and privacy boundaries.</p>
        </article>
    </div>
    <div class="mt-6 rounded-2xl border border-slate-200 px-6 py-5 text-sm leading-7 text-slate-600 dark:border-slate-800 dark:text-slate-300">Edvora is being developed in reviewed milestones. Modules not yet approved for release are intentionally not represented as working actions.</div>
</section>
@endsection
