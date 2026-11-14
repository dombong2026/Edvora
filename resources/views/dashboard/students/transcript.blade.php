@extends('layouts.app')
@section('title', 'Reviewed academic results')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Academic results</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Reviewed academic results</h1>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $profile->display_name }} · {{ $profile->student_number }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('students.show', [$institution, $profile->id]) }}" class="edvora-secondary">Student record</a>
        <a href="{{ route('students.transcript.export', [$institution, $profile->id]) }}" class="edvora-primary">Export results CSV</a>
        <a href="{{ route('students.statements.index', [$institution, $profile->id]) }}" class="edvora-secondary">Request an issued statement</a>
    </div>
</header>
<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Results preview (not an official transcript)</h2>
    <p class="mt-2 text-sm leading-7 text-slate-500 dark:text-slate-400">Only independently published class assessments are included. Unpublished marks and classmates' results are excluded. Official transcripts and examination certification require separate review and issuance functionality.</p>
</section>
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[620px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Academic year / class</th><th class="px-5 py-3">Assessment</th><th class="px-5 py-3">Points</th><th class="px-5 py-3">Published</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($results as $result)
                <tr>
                    <td class="px-5 py-4"><p class="font-semibold">{{ $result->academic_year }}</p><p class="mt-1 text-xs text-slate-500">{{ $result->class_name }}</p></td>
                    <td class="px-5 py-4">{{ $result->title }}</td>
                    <td class="px-5 py-4 font-bold tabular-nums">{{ $result->points_earned }} / {{ $result->max_points }}</td>
                    <td class="px-5 py-4 text-xs">{{ \Carbon\Carbon::parse($result->published_at)->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No published results are available for this student yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
