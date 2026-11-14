@extends('layouts.app')
@section('title', 'Reviewed academic statement')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Private issued statement</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Academic results statement</h1>
        <p class="mt-2 font-mono text-sm text-slate-500 dark:text-slate-400">{{ $statement->statement_number }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('students.statements.index', [$institution, $profile->id]) }}" class="edvora-secondary">Back to statement history</a>
        <a href="{{ route('students.statements.export', [$institution, $profile->id, $statement->id]) }}" class="edvora-primary">Download issued snapshot</a>
    </div>
</header>
<section class="edvora-surface p-7" aria-label="Academic statement content">
    <p class="text-sm font-bold text-blue-600 dark:text-blue-400">School-reviewed results record — not a legally certified transcript</p>
    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-500 dark:text-slate-400">This snapshot was independently issued by the institution from previously published academic results. The values are preserved as recorded on the issuance date. It is not a diploma, digitally signed certificate, or government-validated credential.</p>
    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div><dt class="text-xs font-semibold text-slate-500">Institution</dt><dd class="mt-1 text-sm font-bold">{{ $snapshot['institution'] }}</dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Campus</dt><dd class="mt-1 text-sm font-bold">{{ $snapshot['campus'] }}</dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Student</dt><dd class="mt-1 text-sm font-bold">{{ $snapshot['student_name'] }}</dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Student number</dt><dd class="mt-1 font-mono text-sm">{{ $snapshot['student_number'] }}</dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Academic year</dt><dd class="mt-1 text-sm font-bold">{{ $snapshot['academic_year'] }}</dd></div>
        <div><dt class="text-xs font-semibold text-slate-500">Issued at</dt><dd class="mt-1 text-sm">{{ \Carbon\Carbon::parse($statement->reviewed_at)->format('d M Y, H:i') }}</dd></div>
    </dl>
    <div class="mt-7 overflow-x-auto">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr><th class="px-4 py-3">Subject / class</th><th class="px-4 py-3">Assessment</th><th class="px-4 py-3">Points</th><th class="px-4 py-3">Maximum</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($snapshot['results'] as $result)
                    <tr>
                        <td class="px-4 py-4"><p class="font-semibold">{{ $result['subject'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $result['class'] }}</p></td>
                        <td class="px-4 py-4">{{ $result['assessment'] }}</td>
                        <td class="px-4 py-4 tabular-nums">{{ $result['points'] }}</td>
                        <td class="px-4 py-4 tabular-nums">{{ $result['maximum'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-7 border-t border-slate-200 pt-5 dark:border-slate-800">
        <p class="text-xs font-semibold text-slate-500">Integrity SHA-256</p>
        <p class="mt-2 break-all font-mono text-xs">{{ $statement->snapshot_sha256 }}</p>
    </div>
</section>
@endsection
