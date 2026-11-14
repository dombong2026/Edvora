@extends('layouts.app')
@section('title', $institution->name . ' students')
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Student information · {{ $institution->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Student workspace</h1>
    <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">Only student records that your institutional permissions allow are shown. Guardian access requires explicit independent verification.</p>
    <a class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline" href="{{ route('dashboard.institution', $institution) }}">Return to institution dashboard</a>
</header>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Authorized student profiles">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Authorized student profiles</h2>
        <span class="text-xs text-slate-500">{{ $students->count() }} record(s) shown · maximum 100</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[540px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr><th class="px-6 py-3">Student number</th><th class="px-6 py-3">Name</th><th class="px-6 py-3">Campus</th><th class="px-6 py-3">Record</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($students as $student)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-6 py-4 font-mono text-xs">{{ $student->student_number }}</td>
                        <td class="px-6 py-4 font-semibold">
                            <a href="{{ route('students.show', [$institution, $student]) }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $student->display_name }}</a>
                        </td>
                        <td class="px-6 py-4">{{ $student->campus?->name }}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">{{ ucfirst($student->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">No student profiles are available to this account.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($managedCampuses->isNotEmpty())
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Create a student record</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Use the institution-issued student number and preferred display name. Do not enter health or safeguarding information here. Account and parent links are verified separately.</p>
    <form method="POST" action="{{ route('students.store', $institution) }}" class="mt-6 grid gap-4 sm:grid-cols-3">
        @csrf
        <div>
            <label for="student-campus" class="mb-2 block text-sm font-semibold">Campus</label>
            <select id="student-campus" name="campus_id" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Choose campus</option>
                @foreach($managedCampuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string)old('campus_id') === (string)$campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('campus_id') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="student-number" class="mb-2 block text-sm font-semibold">Student number</label>
            <input id="student-number" name="student_number" type="text" value="{{ old('student_number') }}" maxlength="64" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('student_number') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="student-name" class="mb-2 block text-sm font-semibold">Display name</label>
            <input id="student-name" name="display_name" type="text" value="{{ old('display_name') }}" maxlength="180" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('display_name') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Create student record</button>
        </div>
    </form>
</section>
@endif
@endsection
