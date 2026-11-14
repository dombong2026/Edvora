@extends('layouts.app')
@section('title', $institution->name . ' academics')
@section('content')
<div class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Academic planning · {{ $institution->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Academic catalog</h1>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Manage academic years and subjects securely. Assignments, gradebooks, and student records are future modules and are not available here.</p>
    <a class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline" href="{{ route('dashboard.institution', $institution) }}">Return to institution dashboard</a>
</div>

<div class="grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">Academic years</h2>
            <span class="text-xs text-slate-500">Latest {{ $years->count() }}</span>
        </div>
        <ul class="mt-5 divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($years as $year)
                <li class="flex items-center justify-between gap-4 py-3">
                    <div>
                        <p class="text-sm font-semibold">{{ $year->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $year->code }} · {{ $year->starts_on->format('d M Y') }} — {{ $year->ends_on->format('d M Y') }}</p>
                    </div>
                    <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ ucfirst($year->status) }}</span>
                </li>
            @empty
                <li class="py-6 text-sm text-slate-500">No academic years have been created for this institution.</li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">Subjects</h2>
            <span class="text-xs text-slate-500">Latest {{ $subjects->count() }}</span>
        </div>
        <ul class="mt-5 divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($subjects as $subject)
                <li class="flex items-center justify-between gap-4 py-3">
                    <div>
                        <p class="text-sm font-semibold">{{ $subject->name }}</p>
                        <p class="mt-1 text-xs font-mono text-slate-500">{{ $subject->code }}</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ $subject->is_active ? 'Active' : 'Inactive' }}</span>
                </li>
            @empty
                <li class="py-6 text-sm text-slate-500">No subjects are registered for this institution.</li>
            @endforelse
        </ul>
    </section>
</div>

@if($canManage)
<div class="mt-6 grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-bold">Add an academic year</h2>
        <form method="POST" action="{{ route('academic.years.store', $institution) }}" class="mt-5 space-y-4">
            @csrf
            @foreach(['code' => 'Year code', 'name' => 'Display name'] as $field => $label)
                <div>
                    <label for="year-{{ $field }}" class="mb-2 block text-sm font-semibold">{{ $label }}</label>
                    <input id="year-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $field === 'code' ? 40 : 120 }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error($field) <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="starts_on" class="mb-2 block text-sm font-semibold">Start date</label>
                    <input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
                <div>
                    <label for="ends_on" class="mb-2 block text-sm font-semibold">End date</label>
                    <input id="ends_on" name="ends_on" type="date" required value="{{ old('ends_on') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
            </div>
            @error('ends_on') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Create draft academic year</button>
        </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-bold">Register a subject</h2>
        <form method="POST" action="{{ route('academic.subjects.store', $institution) }}" class="mt-5 space-y-4">
            @csrf
            @foreach(['code' => 'Subject code', 'name' => 'Subject name'] as $field => $label)
                <div>
                    <label for="subject-{{ $field }}" class="mb-2 block text-sm font-semibold">{{ $label }}</label>
                    <input id="subject-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $field === 'code' ? 40 : 150 }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error($field) <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div>
                <label for="description" class="mb-2 block text-sm font-semibold">Description (optional)</label>
                <textarea id="description" name="description" rows="3" maxlength="2000" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description') }}</textarea>
                @error('description') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Register subject</button>
        </form>
    </section>
</div>
@endif
@endsection
