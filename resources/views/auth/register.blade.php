@extends('layouts.app')
@section('title', 'Create account')
@section('content')
<section class="mx-auto max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
    <h1 class="text-3xl font-bold tracking-tight">Create account</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Registration does not grant access to any institution.</p>
    <form method="POST" action="{{ route('register.store') }}" class="mt-7 space-y-4">
        @csrf
        @foreach(['name' => 'Full name', 'email' => 'Email address'] as $field => $label)
            <label class="block text-sm font-semibold" for="{{ $field }}">{{ $label }}
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" required value="{{ old($field) }}" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                @error($field) <span class="mt-1 block text-red-600" role="alert">{{ $message }}</span> @enderror
            </label>
        @endforeach
        @foreach(['password' => 'Password', 'password_confirmation' => 'Confirm password'] as $field => $label)
            <label class="block text-sm font-semibold" for="{{ $field }}">{{ $label }}
                <input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                @error($field) <span class="mt-1 block text-red-600" role="alert">{{ $message }}</span> @enderror
            </label>
        @endforeach
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700">Create account</button>
    </form>
</section>
@endsection
