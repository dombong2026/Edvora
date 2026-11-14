@extends('layouts.app')
@section('title', 'Class register · ' . $session->session_date->toDateString())
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · {{ $section->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">Class register</h1>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $session->session_date->format('d M Y') }} · {{ ucfirst($session->status) }}</p>
    <a href="{{ route('attendance.index', [$institution, $section]) }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">Back to attendance</a>
</header>

@if($canTake && $session->status === 'draft')
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Record student attendance</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">Mark every eligible active student. Records are private drafts until an independent school officer approves the submitted register.</p>
    <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($eligibleEnrollments as $enrollment)
            <form method="POST" action="{{ route('attendance.mark', [$institution, $section, $session]) }}" class="flex flex-wrap items-center justify-between gap-3 py-4">
                @csrf
                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                <div>
                    <p class="text-sm font-semibold">{{ $enrollment->student->display_name }}</p>
                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $enrollment->student->student_number }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label for="attendance-{{ $enrollment->id }}" class="sr-only">Attendance status for {{ $enrollment->student->display_name }}</label>
                    <select id="attendance-{{ $enrollment->id }}" name="status" required class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="">Choose status</option>
                        @foreach(\App\Services\ClassAttendanceService::STATUSES as $status)
                            <option value="{{ $status }}" @selected($existingMarks->get($enrollment->id)?->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-700">Save mark</button>
                </div>
            </form>
        @empty
            <p class="py-5 text-sm text-slate-500">There are no eligible active enrollments for this date.</p>
        @endforelse
    </div>
    @error('status') <p role="alert" class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('enrollment') <p role="alert" class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('attendance') <p role="alert" class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
    <form method="POST" action="{{ route('attendance.submit', [$institution, $section, $session]) }}" class="mt-4 border-t border-slate-200 pt-5 dark:border-slate-800">
        @csrf
        <button type="submit" class="rounded-xl border border-blue-200 px-5 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">Submit complete register for approval</button>
    </form>
</section>
@endif

@if($canApprove && $session->status === 'submitted' && (int)$session->created_by !== (int)auth()->id() && (int)$session->submitted_by !== (int)auth()->id())
<section class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950">
    <h2 class="text-lg font-bold text-amber-900 dark:text-amber-200">Independent attendance review</h2>
    <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">Verify the class and submitted records before approval. Approved attendance becomes read-only to the teacher and visible only to the corresponding student or verified guardian.</p>
    <form method="POST" action="{{ route('attendance.approve', [$institution, $section, $session]) }}" class="mt-5">
        @csrf
        <button type="submit" class="rounded-xl bg-amber-700 px-5 py-3 text-sm font-bold text-white hover:bg-amber-800">Approve attendance register</button>
    </form>
</section>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">{{ $canTake || $canApprove ? 'Authorized register review' : 'Your approved attendance' }}</h2>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $canTake || $canApprove ? 'Visible only to the assigned teacher and authorized leadership.' : 'No classmates or other students are displayed.' }}</p>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[380px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-800/40">
                <tr><th class="px-3 py-3">Student number</th><th class="px-3 py-3">Student</th><th class="px-3 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($marks as $mark)
                    <tr>
                        <td class="px-3 py-3 font-mono text-xs">{{ $mark->enrollment->student->student_number }}</td>
                        <td class="px-3 py-3 font-semibold">{{ $mark->enrollment->student->display_name }}</td>
                        <td class="px-3 py-3">{{ ucfirst($mark->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-8 text-center text-sm text-slate-500">No accessible attendance marks yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
