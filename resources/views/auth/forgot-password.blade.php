@extends('layouts.app')
@section('title', 'Reset your password')
@section('content')
<section class="mx-auto max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-lg dark:border-slate-800 dark:bg-slate-900">
    <h1 class="text-2xl font-bold">Reset your password</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Enter the email associated with your account.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-semibold" for="email">Email address</label>
        <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
        @error('email') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white">Send reset link</button>
    </form>
</section>
@endsection
