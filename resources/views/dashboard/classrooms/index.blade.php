@extends('layouts.app')
@section('title', $institution->name . ' classrooms')
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · Academic operations</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Classrooms</h1>
    <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">View your assigned classrooms. Student and guardian accounts cannot access another class or its roster.</p>
    <a href="{{ route('dashboard.institution', $institution) }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">Return to dashboard</a>
</header>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Accessible classes</h2>
        <span class="text-xs text-slate-500">{{ $sections->count() }} shown · maximum 100</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[650px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-6 py-3">Class</th><th class="px-6 py-3">Subject</th><th class="px-6 py-3">Campus</th><th class="px-6 py-3">Teacher</th><th class="px-6 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($sections as $section)
                    <tr>
                        <td class="px-6 py-4">
                            <a href="{{ route('classrooms.show', [$institution, $section]) }}" class="font-semibold text-blue-600 hover:underline">{{ $section->name }}</a>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $section->code }}</p>
                        </td>
                        <td class="px-6 py-4">{{ $section->subject?->name }}</td>
                        <td class="px-6 py-4">{{ $section->campus?->name }}</td>
                        <td class="px-6 py-4">{{ $section->teacher?->name ?? 'Unassigned' }}</td>
                        <td class="px-6 py-4"><span class="rounded-full border border-slate-200 px-2.5 py-1 text-xs font-semibold dark:border-slate-700">{{ ucfirst($section->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">No authorized classes are assigned to this account.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@if($canManage)
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Create a draft class section</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">An active academic year and subject are required. A verified teacher must be assigned before activation.</p>
    <form method="POST" action="{{ route('classrooms.store', $institution) }}" class="mt-6 grid gap-4 md:grid-cols-3">
        @csrf
        <div>
            <label for="section-campus" class="mb-2 block text-sm font-semibold">Campus</label>
            <select id="section-campus" name="campus_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Select campus</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string)old('campus_id')===(string)$campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('campus_id') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="section-year" class="mb-2 block text-sm font-semibold">Academic year</label>
            <select id="section-year" name="academic_year_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Choose active year</option>
                @foreach($years as $year)
                    <option value="{{ $year->id }}" @selected((string)old('academic_year_id')===(string)$year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
            @error('academic_year_id') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="section-subject" class="mb-2 block text-sm font-semibold">Subject</label>
            <select id="section-subject" name="academic_subject_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Choose subject</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected((string)old('academic_subject_id')===(string)$subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('academic_subject_id') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="section-code" class="mb-2 block text-sm font-semibold">Code</label>
            <input id="section-code" name="code" maxlength="40" required value="{{ old('code') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('code') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="section-name" class="mb-2 block text-sm font-semibold">Class name</label>
            <input id="section-name" name="name" maxlength="150" required value="{{ old('name') }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('name') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="section-capacity" class="mb-2 block text-sm font-semibold">Capacity</label>
            <input id="section-capacity" name="capacity" type="number" min="1" max="150" required value="{{ old('capacity', 40) }}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('capacity') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <button class="md:col-span-3 justify-self-start rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700" type="submit">Create draft class</button>
    </form>
</section>
@endif
@endsection
