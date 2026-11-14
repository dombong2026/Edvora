@extends('layouts.app')
@section('title', 'My account')
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Personal workspace</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">My account</h1>
    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">Manage your profile, protect your sign-in, and open only the institutions that have granted you access.</p>
</header>
<div class="grid gap-5 lg:grid-cols-3">
    <section class="edvora-surface p-6 lg:col-span-2" aria-labelledby="profile-title">
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-bold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h2 id="profile-title" class="text-lg font-bold">Profile information</h2>
                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('account.profile.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label for="account-name" class="mb-2 block text-sm font-semibold">Display name</label>
                <input id="account-name" name="name" required minlength="2" maxlength="255" autocomplete="name" value="{{ old('name', $user->name) }}" class="edvora-field">
                @error('name')<p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs leading-6 text-slate-500 dark:text-slate-400">Your verified email and institution assignments cannot be edited here. Contact an authorized administrator to request an identity change.</p>
            <button type="submit" class="edvora-primary">Save profile</button>
        </form>
    </section>
    <section class="edvora-surface p-6">
        <h2 class="text-lg font-bold">Sign-in security</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Authenticator verification</p>
        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $user->mfa_enabled_at ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
            {{ $user->mfa_enabled_at ? 'Enabled' : 'Not enabled' }}
        </span>
        <a href="{{ route('mfa.setup') }}" class="edvora-secondary mt-6 w-full justify-center">Manage two-factor authentication</a>
        @if($user->mfa_enabled_at)
            <a href="{{ route('mfa.recovery') }}" class="mt-3 block text-center text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">Recovery codes</a>
        @endif
    </section>
</div>
<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <section class="edvora-surface p-6" aria-labelledby="password-title">
        <h2 id="password-title" class="text-lg font-bold">Change password</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Confirm your current password before choosing a new one.</p>
        <form action="{{ route('account.password.update') }}" method="POST" class="mt-5 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="current-password" class="mb-2 block text-sm font-semibold">Current password</label>
                <input id="current-password" name="current_password" type="password" autocomplete="current-password" required class="edvora-field">
                @error('current_password')<p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-password" class="mb-2 block text-sm font-semibold">New password</label>
                <input id="new-password" name="password" type="password" autocomplete="new-password" required class="edvora-field">
                @error('password')<p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="confirm-password" class="mb-2 block text-sm font-semibold">Confirm new password</label>
                <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required class="edvora-field">
            </div>
            <button class="edvora-primary" type="submit">Update password</button>
        </form>
    </section>
    <section class="edvora-surface p-6" aria-labelledby="workspace-title">
        <div class="flex items-center justify-between gap-3">
            <h2 id="workspace-title" class="text-lg font-bold">My institutions</h2>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $memberships->count() }} authorized</span>
        </div>
        <label for="workspace-search" class="mt-5 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Find an institution</label>
        <input id="workspace-search" type="search" data-workspace-search placeholder="Search my workspaces" class="edvora-field mt-2">
        <div class="mt-4 space-y-2">
            @forelse($memberships as $membership)
                <a data-workspace-card data-workspace-name="{{ mb_strtolower($membership->institution->name) }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold hover:border-blue-400 hover:bg-blue-50 dark:border-slate-700 dark:hover:border-blue-700 dark:hover:bg-blue-950/30" href="{{ route('dashboard.institution', $membership->institution) }}">
                    <span class="truncate">{{ $membership->institution->name }}</span>
                    <span class="shrink-0 text-blue-600 dark:text-blue-400" aria-hidden="true">→</span>
                </a>
            @empty
                <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">No active institutions are assigned to this account.</p>
            @endforelse
            <p data-workspace-empty hidden class="text-sm text-slate-500 dark:text-slate-400">No matching institution in your authorized list.</p>
        </div>
        @if($user->isPlatformAdmin())
            <a href="{{ route('dashboard.platform') }}" class="edvora-secondary mt-5 w-full justify-center">Open platform console</a>
        @endif
    </section>
</div>
<section class="edvora-surface mt-5 overflow-hidden" aria-labelledby="activity-title">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <div>
            <h2 id="activity-title" class="text-lg font-bold">My recent account actions</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only actions performed by your account. Sensitive event details are hidden.</p>
        </div>
    </div>
    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($recentEvents as $event)
            <li class="flex flex-wrap justify-between gap-3 px-6 py-4 text-sm">
                <span class="font-semibold">{{ str($event->event_type)->replace('.', ' · ')->headline() }}</span>
                <time class="text-xs text-slate-500 dark:text-slate-400" datetime="{{ $event->created_at }}">{{ \Carbon\Carbon::parse($event->created_at)->format('d M Y, H:i') }}</time>
            </li>
        @empty
            <li class="px-6 py-8 text-sm text-slate-500 dark:text-slate-400">No recorded actions yet.</li>
        @endforelse
    </ul>
    @if($recentEvents->hasPages())
        <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $recentEvents->links() }}</div>
    @endif
</section>
@endsection
