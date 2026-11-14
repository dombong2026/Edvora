@extends('layouts.app')
@section('title', 'Class · ' . $section->name)
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · {{ $section->campus->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $section->name }}</h1>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $section->code }} · {{ $section->subject->name }} · {{ $section->year->name }}</p>
    <a href="{{ route('classrooms.index', $institution) }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">Back to classrooms</a>
</header>
<section class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500">Status</p>
        <p class="mt-3 text-xl font-bold">{{ ucfirst($section->status) }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500">Assigned teacher</p>
        <p class="mt-3 text-xl font-bold">{{ $section->teacher?->name ?? 'Unassigned' }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500">Class capacity</p>
        <p class="mt-3 text-xl font-bold">{{ $section->capacity }}</p>
    </div>
</section>

@if($canViewRoster)
<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Authorized class roster</h2>
        <span class="text-xs text-slate-500">{{ $enrollments->count() }} active enrollment(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[500px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/40">
                <tr><th class="px-6 py-3">Student number</th><th class="px-6 py-3">Name</th><th class="px-6 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($enrollments as $enrollment)
                    <tr>
                        <td class="px-6 py-4 font-mono text-xs">{{ $enrollment->student->student_number }}</td>
                        <td class="px-6 py-4 font-semibold">{{ $enrollment->student->display_name }}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">Enrolled</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-slate-500">No active enrollments in this class.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@else
<p class="mt-6 rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">Your access is limited to your own class details. Classmate names and class rosters are restricted to authorized staff.</p>
@endif


<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold">Weekly class timetable</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Recurring schedule in the institution's local time. Slots are only visible to accounts authorized for this class.</p>
        </div>
        <a href="{{ route('attendance.index', [$institution, $section]) }}" class="inline-flex rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">Attendance registers</a>
    </div>
    <ul class="mt-5 divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($timetableSlots as $slot)
            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                    <p class="text-sm font-semibold">{{ ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$slot->weekday - 1] }}
                        · {{ substr($slot->starts_at, 0, 5) }} – {{ substr($slot->ends_at, 0, 5) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $slot->room_name ?: 'Room not assigned' }}</p>
                </div>
                <span class="text-xs text-slate-500">{{ $section->subject->name }}</span>
            </li>
        @empty
            <li class="py-5 text-sm text-slate-500">No regular timetable slots have been scheduled.</li>
        @endforelse
    </ul>

    @if($canSchedule)
    <form method="POST" action="{{ route('timetable.store', [$institution, $section]) }}" class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2 lg:grid-cols-5 dark:border-slate-800">
        @csrf
        <div>
            <label for="schedule-weekday" class="mb-2 block text-sm font-semibold">Day</label>
            <select id="schedule-weekday" name="weekday" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $idx => $day)
                    <option value="{{ $idx+1 }}" @selected((int)old('weekday', 1) === $idx+1)>{{ $day }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="schedule-start" class="mb-2 block text-sm font-semibold">Start time</label>
            <input id="schedule-start" name="starts_at" type="time" value="{{ old('starts_at', '08:00') }}" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('starts_at') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="schedule-end" class="mb-2 block text-sm font-semibold">End time</label>
            <input id="schedule-end" name="ends_at" type="time" value="{{ old('ends_at', '09:00') }}" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('ends_at') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="schedule-room" class="mb-2 block text-sm font-semibold">Room (optional)</label>
            <input id="schedule-room" name="room_name" maxlength="100" value="{{ old('room_name') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('room_name') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="self-end rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Add timetable</button>
    </form>
    @endif
</section>

@if($canManage)
<section class="mt-6 grid gap-5 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-bold">Assign verified teacher</h2>
        <form method="POST" action="{{ route('classrooms.teacher.assign', [$institution, $section]) }}" class="mt-5 space-y-4">
            @csrf
            <label for="teacher-email" class="block text-sm font-semibold">Teacher or professor account email</label>
            <input id="teacher-email" name="email" type="email" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('email') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Assign teacher</button>
        </form>
        @if($section->status === 'draft')
            <form method="POST" action="{{ route('classrooms.activate', [$institution, $section]) }}" class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                @csrf
                <p class="mb-3 text-sm text-slate-500">Activate only after confirming the academic year, campus, subject and teacher assignment.</p>
                <button type="submit" class="rounded-xl border border-blue-200 px-5 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">Activate class</button>
                @error('section') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @endif
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-bold">Enroll a student</h2>
        <p class="mt-2 text-sm text-slate-500">Only active students from the same campus may enroll in an active class. Capacity is enforced.</p>
        @if($section->status === 'active')
            <form method="POST" action="{{ route('classrooms.students.enroll', [$institution, $section]) }}" class="mt-5 space-y-4">
                @csrf
                <label for="class-student-number" class="block text-sm font-semibold">Institution student number</label>
                <input id="class-student-number" name="student_number" maxlength="64" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('student_number') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('student') <p role="alert" class="text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Enroll student</button>
            </form>
        @else
            <p class="mt-5 text-sm text-slate-500">Enrollment becomes available after class activation.</p>
        @endif
    </div>
</section>
@endif
@endsection
