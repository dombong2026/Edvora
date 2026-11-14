@extends('layouts.app')
@section('title', 'Assessment review · ' . $section->name)
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · {{ $section->campus->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Assessments · {{ $section->name }}</h1>
    <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">Draft scores are restricted to assigned staff. Published assessments are visible only to the enrolled student and their verified guardian.</p>
    <a href="{{ route('classrooms.show', [$institution, $section]) }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">Return to class</a>
</header>
@if($canWrite)
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Create draft assessment</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">Set the marking maximum. No score will be released without independent school leadership approval.</p>
    <form method="POST" action="{{ route('assessments.store', [$institution, $section]) }}" class="mt-5 grid gap-4 sm:grid-cols-3">
        @csrf
        <div>
            <label for="assessment-code" class="mb-2 block text-sm font-semibold">Code</label>
            <input id="assessment-code" name="code" value="{{ old('code') }}" maxlength="40" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('code') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="assessment-title" class="mb-2 block text-sm font-semibold">Assessment title</label>
            <input id="assessment-title" name="title" value="{{ old('title') }}" maxlength="150" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('title') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="assessment-max" class="mb-2 block text-sm font-semibold">Maximum points</label>
            <input id="assessment-max" name="max_points" type="number" min="0.01" max="10000" step="0.01" value="{{ old('max_points', '100') }}" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('max_points') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="sm:col-span-3 justify-self-start rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Create private draft</button>
    </form>
</section>
@endif

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Accessible assessments</h2>
        <span class="text-xs text-slate-500">{{ $assessments->count() }} shown · maximum 100</span>
    </div>
    <ul class="divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($assessments as $assessment)
            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <div>
                    <p class="text-sm font-semibold">{{ $assessment->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $assessment->code }} · Maximum {{ $assessment->max_points }} points · {{ ucfirst($assessment->status) }}</p>
                </div>
                <a href="{{ route('assessments.show', [$institution, $section, $assessment]) }}" class="rounded-xl border border-blue-200 px-4 py-2.5 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">View assessment</a>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-slate-500">No assessment records are available to this account.</li>
        @endforelse
    </ul>
</section>
@endsection
