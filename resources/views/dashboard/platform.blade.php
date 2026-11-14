@extends('layouts.app')
@section('title', 'Platform dashboard')
@section('content')
<div class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Platform Administration</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Edvora platform</h1>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Your platform administrator designation is active. Institution records still require separate, explicit membership.</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Foundation workspace</h2>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Platform configuration and onboarding controls will be introduced after independent permissions acceptance. Cross-institution student and school information is not exposed here.</p>
</div>
@endsection
