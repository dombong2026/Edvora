@extends('layouts.app')
@section('title', 'Assessment · ' . $assessment->title)
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · {{ $section->name }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $assessment->title }}</h1>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">Maximum {{ $assessment->max_points }} points · {{ ucfirst($assessment->status) }}</p>
    <a href="{{ route('assessments.index', [$institution, $section]) }}" class="mt-4 inline-block text-sm font-semibold text-blue-600 hover:underline">Back to assessments</a>
</header>

@if($canWrite && $assessment->status === 'draft')
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Enter private draft scores</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">Each active enrolled student needs an explicit score. A zero is a score, not a missing result. Submitted results are locked until independently reviewed.</p>
    <div class="mt-5 divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($eligibleEnrollments as $enrollment)
            <form method="POST" action="{{ route('assessments.record', [$institution, $section, $assessment]) }}" class="flex flex-wrap items-center justify-between gap-3 py-4">
                @csrf
                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                <div>
                    <p class="text-sm font-semibold">{{ $enrollment->student->display_name }}</p>
                    <p class="mt-1 font-mono text-xs text-slate-500">{{ $enrollment->student->student_number }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <label for="score-{{ $enrollment->id }}" class="sr-only">Points for {{ $enrollment->student->display_name }}</label>
                    <input id="score-{{ $enrollment->id }}" name="points_earned" type="number" min="0" max="{{ $assessment->max_points }}" step="0.01" required value="{{ $existingResults->get($enrollment->id)?->points_earned }}" class="w-32 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-700">Save score</button>
                </div>
            </form>
        @empty
            <p class="py-5 text-sm text-slate-500">No active class enrollment is available for grading.</p>
        @endforelse
    </div>
    @error('points_earned') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('points') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('result') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('assessment') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    <form method="POST" action="{{ route('assessments.submit', [$institution, $section, $assessment]) }}" class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
        @csrf
        <button type="submit" class="rounded-xl border border-blue-200 px-5 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">Submit complete scores for review</button>
    </form>
</section>
@endif

@if($canReview && $assessment->status === 'submitted' && (int)$assessment->created_by !== (int)auth()->id() && (int)$assessment->submitted_by !== (int)auth()->id())
<section class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950">
    <h2 class="text-lg font-bold text-amber-900 dark:text-amber-200">Independent grading review</h2>
    <p class="mt-2 text-sm leading-6 text-amber-800 dark:text-amber-300">Confirm all student results and the assessment maximum. Publication allows authorized families to view only their own grades.</p>
    <form method="POST" action="{{ route('assessments.publish', [$institution, $section, $assessment]) }}" class="mt-5">
        @csrf
        <button type="submit" class="rounded-xl bg-amber-700 px-5 py-3 text-sm font-bold text-white hover:bg-amber-800">Approve and publish scores</button>
    </form>
</section>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">{{ $canWrite || $canReview ? 'Authorized assessment results' : 'Your reviewed score' }}</h2>
    <p class="mt-2 text-sm text-slate-500">{{ $canWrite || $canReview ? 'Private grades for assigned teachers and verified school leadership.' : 'Only your own score is shown. Other students’ grades are confidential.' }}</p>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[380px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500 dark:bg-slate-800/40">
                <tr><th class="px-3 py-3">Student number</th><th class="px-3 py-3">Student</th><th class="px-3 py-3">Earned points</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($results as $result)
                    <tr>
                        <td class="px-3 py-3 font-mono text-xs">{{ $result->enrollment->student->student_number }}</td>
                        <td class="px-3 py-3 font-semibold">{{ $result->enrollment->student->display_name }}</td>
                        <td class="px-3 py-3">{{ $result->points_earned }} / {{ $assessment->max_points }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-8 text-center text-sm text-slate-500">No accessible grades have been recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-5 text-xs text-slate-500">This is an individual class assessment result, not a final transcript or academic promotion decision.</p>
</section>
@include('dashboard.partials.academic-corrections', [
    'kind' => 'assessment',
    'items' => $results,
    'canRequest' => $canWrite && $assessment->status === 'published',
    'canReview' => $canReview,
])
@endsection
