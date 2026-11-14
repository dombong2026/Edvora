@extends('layouts.app')
@section('title', 'School Management & Education ERP')
@section('content')
<section class="mx-auto max-w-5xl py-12 sm:py-24">
    <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">Education operations · Edvora v0.0.1</span>
    <h1 class="mt-7 max-w-4xl text-4xl font-bold leading-tight tracking-tight sm:text-6xl">One connected workspace for modern educational institutions.</h1>
    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">Edvora brings identity, campus access, and academic administration into a secure, consistent foundation for school operations.</p>
    <div class="mt-10 flex flex-wrap gap-3">
        @auth
            <a href="{{ route('dashboard') }}" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700">Open dashboard</a>
        @else
            <a href="{{ route('login') }}" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700">Sign in to Edvora</a>
        @endauth
    </div>
</section>
@endsection
