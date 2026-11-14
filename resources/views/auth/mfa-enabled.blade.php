@extends('layouts.app')
@section('title', 'Account security')
@section('content')
<section class="mx-auto max-w-2xl rounded-3xl border border-emerald-200 bg-white p-8 shadow-sm dark:border-emerald-900 dark:bg-slate-900">
    <p class="text-xs font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Security status</p>
    <h1 class="mt-3 text-2xl font-bold">Two-factor authentication is active</h1>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">This account is protected with time-based codes and single-use recovery codes. For emergency recovery or security reset, contact a verified platform security officer. Self-service deactivation is not available in this version.</p>
    <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white">Return to dashboard</a>
</section>
@endsection
