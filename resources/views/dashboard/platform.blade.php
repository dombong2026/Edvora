@extends('layouts.app')
@section('title', 'Platform dashboard')
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Platform administration</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Edvora platform</h1>
    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">Platform security and onboarding overview. Institution records and student information require separate membership and permissions.</p>
</header>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <div class="edvora-surface p-6">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Supported account roles</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ $roleCount }}</p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">From the real role catalog</p>
    </div>
    <div class="edvora-surface p-6">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Your authorized institutions</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ $authorizedInstitutionCount }}</p>
        <a href="{{ route('account.index') }}#workspace-title" class="mt-2 inline-block text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Open my institution list</a>
    </div>
    <div class="edvora-surface p-6">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Sign-in protection</p>
        <p class="mt-4 text-2xl font-bold">{{ auth()->user()->mfa_enabled_at ? 'Authenticator enabled' : 'Setup required' }}</p>
        <a href="{{ route('mfa.setup') }}" class="mt-2 inline-block text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Manage verification</a>
    </div>
</div>
<div class="mt-6 grid gap-5 xl:grid-cols-2">
    <section class="edvora-surface p-6">
        <h2 class="text-lg font-bold">Platform controls</h2>
        <div class="mt-5 space-y-3">
            <a href="{{ route('account.index') }}" class="edvora-list-link"><span>My profile and password</span><span aria-hidden="true">→</span></a>
            <a href="{{ route('mfa.setup') }}" class="edvora-list-link"><span>Authenticator security</span><span aria-hidden="true">→</span></a>
            <a href="{{ route('account.index') }}#activity-title" class="edvora-list-link"><span>My activity history</span><span aria-hidden="true">→</span></a>
            <a href="{{ route('public.help') }}" class="edvora-list-link"><span>Help & platform information</span><span aria-hidden="true">→</span></a>
        </div>
    </section>
    <section class="edvora-surface p-6">
        <h2 class="text-lg font-bold">Platform configuration</h2>
        <dl class="mt-5 divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500 dark:text-slate-400">Version</dt><dd class="font-semibold">v{{ config('edvora.version') }}</dd></div>
            <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500 dark:text-slate-400">Public registration</dt><dd class="font-semibold">{{ config('edvora.self_registration') ? 'Enabled' : 'Invitation only' }}</dd></div>
            <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500 dark:text-slate-400">Institution access</dt><dd class="font-semibold">Explicit membership</dd></div>
        </dl>
        <p class="mt-5 text-xs leading-6 text-slate-500 dark:text-slate-400">Enrollment, financial administration, and global institution-management actions are not enabled in this release. No simulated controls are shown.</p>
    </section>
</div>
@endsection
