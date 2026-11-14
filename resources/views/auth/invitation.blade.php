@extends('layouts.app')
@section('title', 'Institution invitation')
@section('content')
<section class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Private institution invitation</p>
    <h1 class="mt-3 text-2xl font-bold">Join {{ $invitation->institution->name }}</h1>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">This invitation is for <strong>{{ $invitation->email }}</strong> to join as <strong>{{ $invitation->role->name }}</strong>{{ $invitation->campus ? ' at '.$invitation->campus->name : '' }}. It expires {{ $invitation->expires_at->format('d M Y H:i') }} (server time).</p>
    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="mt-7 space-y-4">
        @csrf
        @guest
            <p class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200">Create your Edvora identity. Your email is verified by this invitation link. No additional role will be granted.</p>
            <div>
                <label for="name" class="mb-2 block text-sm font-semibold">Full name</label>
                <input id="name" name="name" value="{{ old('name') }}" type="text" autocomplete="name" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                @error('name') <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            @foreach(['password' => 'Choose password', 'password_confirmation' => 'Confirm password'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="mb-2 block text-sm font-semibold">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                    @error($field) <p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        @else
            <p class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200">Signed in as {{ auth()->user()->email }}. Accepting adds only the invited institution role.</p>
        @endguest
        <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Accept invitation</button>
    </form>
</section>
@endsection
