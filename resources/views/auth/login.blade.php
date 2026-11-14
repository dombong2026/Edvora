@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<section class="mx-auto max-w-md rounded-3xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-900/5 sm:p-10 dark:border-slate-800 dark:bg-slate-900">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Secure access</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Welcome back</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Sign in with your institution account.</p>
    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold">Email address</label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-950">
            @error('email') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="mb-2 block text-sm font-semibold">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-slate-700 dark:bg-slate-950">
            @error('password') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2 text-slate-600 dark:text-slate-300"><input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Remember me</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">Forgot password?</a>
        </div>
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Sign in securely</button>
    </form>
    @if(config('edvora.self_registration'))
        <p class="mt-6 text-center text-sm text-slate-500">New account? <a class="font-semibold text-blue-600" href="{{ route('register') }}">Register</a></p>
    @endif
</section>
@endsection
