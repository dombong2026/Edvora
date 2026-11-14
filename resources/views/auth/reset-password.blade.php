@extends('layouts.app')
@section('title', 'Set a new password')
@section('content')
<section class="mx-auto max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-lg dark:border-slate-800 dark:bg-slate-900">
    <h1 class="text-2xl font-bold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @foreach(['email' => 'Email address', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'] as $field => $label)
            <label for="{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'password' }}" required value="{{ $field === 'email' ? old('email', $email) : '' }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
            @error($field) <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endforeach
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white">Update password</button>
    </form>
</section>
@endsection
