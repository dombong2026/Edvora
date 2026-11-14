@extends('layouts.app')
@section('title', 'Exam schedules')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · {{ $section->campus->name }}</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Examination timetable</h1>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $section->name }} · {{ $section->year->name }} · {{ $section->subject->name }}</p>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500 dark:text-slate-400">A live database-backed draft → submitted → independently published schedule. Students and guardians see published sessions only. Marks stay in the separately reviewed assessment workspace.</p>
    </div>
    <a href="{{ route('classrooms.show', [$institution, $section]) }}" class="edvora-secondary">Back to class</a>
</header>
@if($canWrite)
<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Schedule an exam draft</h2>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">An authorized second officer must publish the schedule. Room conflicts are checked against every published exam in the same campus.</p>
    <form method="POST" action="{{ route('exams.store', [$institution, $section]) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @csrf
        <div><label for="exam-code" class="mb-2 block text-sm font-semibold">Exam code</label><input id="exam-code" name="code" required maxlength="40" pattern="[A-Za-z0-9_-]+" value="{{ old('code') }}" class="edvora-field">@error('code')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="xl:col-span-2"><label for="exam-title" class="mb-2 block text-sm font-semibold">Exam title</label><input id="exam-title" name="title" required minlength="3" maxlength="150" value="{{ old('title') }}" class="edvora-field">@error('title')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="exam-date" class="mb-2 block text-sm font-semibold">Exam date</label><input id="exam-date" name="exam_date" type="date" required value="{{ old('exam_date') }}" min="{{ max(now()->toDateString(), $section->year->starts_on->toDateString()) }}" max="{{ $section->year->ends_on->toDateString() }}" class="edvora-field">@error('exam_date')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="exam-start" class="mb-2 block text-sm font-semibold">Start</label><input id="exam-start" name="starts_at" type="time" required value="{{ old('starts_at', '08:00') }}" class="edvora-field">@error('starts_at')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="exam-end" class="mb-2 block text-sm font-semibold">End</label><input id="exam-end" name="ends_at" type="time" required value="{{ old('ends_at', '09:00') }}" class="edvora-field">@error('ends_at')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="exam-room" class="mb-2 block text-sm font-semibold">Room</label><input id="exam-room" name="room" required maxlength="80" pattern="[A-Za-z0-9 _-]+" value="{{ old('room') }}" class="edvora-field">@error('room')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="flex items-end"><button type="submit" class="edvora-primary">Create exam draft</button></div>
    </form>
</section>
@endif
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">{{ $canWrite || $canReview ? 'Exam schedules and reviews' : 'Published exams' }}</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[740px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-5 py-3">Exam</th><th class="px-5 py-3">Date & time</th><th class="px-5 py-3">Room</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($records as $exam)
                <tr>
                    <td class="px-5 py-4"><p class="font-bold">{{ $exam->title }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $exam->code }}</p></td>
                    <td class="px-5 py-4">{{ \Carbon\Carbon::parse($exam->exam_date)->format('d M Y') }}<p class="mt-1 text-xs text-slate-500">{{ substr($exam->starts_at,0,5) }}–{{ substr($exam->ends_at,0,5) }}</p></td>
                    <td class="px-5 py-4">{{ $exam->room }}</td>
                    <td class="px-5 py-4 font-semibold">{{ ucfirst($exam->status) }}</td>
                    <td class="px-5 py-4">
                        @if($canWrite && $exam->status === 'draft')
                            <form method="POST" action="{{ route('exams.submit', [$institution, $section, $exam->id]) }}">
                                @csrf<button type="submit" class="edvora-primary">Submit for review</button>
                            </form>
                        @elseif($canReview && $exam->status === 'submitted' && (int)$exam->created_by !== (int)auth()->id() && (int)$exam->submitted_by !== (int)auth()->id())
                            <div class="flex flex-wrap gap-2">
                                @foreach(['publish'=>'Publish','reject'=>'Reject'] as $decision => $label)
                                <form method="POST" action="{{ route('exams.review', [$institution, $section, $exam->id]) }}">
                                    @csrf<input type="hidden" name="decision" value="{{ $decision }}">
                                    <button type="submit" class="{{ $decision === 'publish' ? 'edvora-primary' : 'edvora-secondary' }}">{{ $label }}</button>
                                </form>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $exam->status === 'submitted' ? 'Review pending another officer' : 'No action' }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No exams are available for this class.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $records->links() }}</div>@endif
</section>
@endsection
