@extends('layouts.app')
@section('title', 'Two-factor verification')
@section('content')
<section class="mx-auto max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Account protection</p>
    <h1 class="mt-3 text-2xl font-bold">Verify your identity</h1>
    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">Enter your authenticator app's six-digit code or a single-use backup recovery code.</p>
    <form method="POST" action="{{ route('mfa.challenge.complete') }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label for="code" class="mb-2 block text-sm font-semibold">Authentication or recovery code</label>
            <input id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" autofocus required maxlength="40" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 font-mono text-lg tracking-wider dark:border-slate-700 dark:bg-slate-950">
            @error('code') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Verify and sign in</button>
    </form>
    <form method="POST" action="{{ route('mfa.challenge.cancel') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-slate-500 underline hover:text-slate-900 dark:hover:text-slate-100">Cancel sign-in</button>
    </form>
</section>
@endsection
