@extends('layouts.app')
@section('title', 'Set up two-factor authentication')
@section('content')
<section class="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Security setup</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Protect your Edvora account</h1>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Two-factor authentication is required before using privileged institution workspaces. In your authenticator app, choose to add an account manually using a time-based (TOTP) secret.</p>
    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Manual setup secret (do not share)</p>
        <p class="mt-3 select-all break-all font-mono text-lg font-semibold tracking-wider" aria-label="Authenticator secret">{{ $secret }}</p>
        <p class="mt-3 text-xs leading-5 text-slate-500">Type: time-based · Issuer: Edvora · 6 digits · 30 seconds. You can also use this provisioning URI in a compatible authenticator: <span class="block mt-2 select-all break-all font-mono text-xs">{{ $provisioningUri }}</span></p>
    </div>
    <form method="POST" action="{{ route('mfa.enable') }}" class="mt-7 space-y-4">
        @csrf
        <div>
            <label for="password" class="mb-2 block text-sm font-semibold">Confirm your current password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
            @error('password') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="code" class="mb-2 block text-sm font-semibold">Six-digit authenticator code</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono dark:border-slate-700 dark:bg-slate-950">
            @error('code') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Enable two-factor authentication</button>
    </form>
</section>
@endsection
