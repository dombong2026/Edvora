@extends('layouts.app')
@section('title', 'Platform features')
@section('content')
<section class="rounded-[2rem] border border-blue-100 bg-gradient-to-br from-white via-blue-50 to-indigo-100 px-6 py-12 sm:px-12 dark:border-slate-800 dark:from-slate-900 dark:via-blue-950 dark:to-slate-950">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Edvora capabilities</p>
    <h1 class="mt-4 max-w-3xl text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">Purpose-built school workspaces</h1>
    <p class="mt-5 max-w-3xl text-base leading-8 text-slate-600 dark:text-slate-300">Edvora brings verified roles, academic operations and accountable school workflows into a consistent education ERP foundation.</p>
    <div class="mt-8 flex flex-wrap gap-3">
        @auth<a href="{{ route('dashboard') }}" class="edvora-primary">Open my dashboard</a>
        @else<a href="{{ route('login') }}" class="edvora-primary">Sign in</a>@endauth
        <a href="{{ route('public.roles') }}" class="edvora-secondary">Explore account roles</a>
    </div>
</section>
<section class="py-12" aria-labelledby="available-features">
    <h2 id="available-features" class="text-2xl font-bold tracking-tight">Implemented workspaces</h2>
    <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">Only functions already implemented in the development branch are listed below. Access depends on school authorization.</p>
    @php
        $features = [
            ['Identity and security', 'Institution invitations, scoped memberships, MFA, password management, account history and controlled roles.', 'security'],
            ['Academic records & reports', 'Academic years, class sections, attendance, independently published assessments, private student results preview and leadership aggregate CSV reports.', 'academic'],
            ['School-reviewed academic statements', 'Authorized student or guardian requests, independent leadership issue/revocation, private immutable result snapshots and CSV export. Not legally certified transcripts.', 'academic'],
            ['Student lifecycle', 'Independent review of withdrawals, promotions into available classes and restricted same-institution transfers for unlinked records.', 'students'],
            ['Exam scheduling', 'Class exam drafts, staff submission, separate leadership publication and campus room collision checks.', 'classrooms'],
            ['Library circulation', 'Campus catalog, physical copy inventory, secure member checkout and return records.', 'library'],
            ['Operational workspaces', 'Fee proposal review, campus visitor check-in, admissions enquiries and leadership task boards.', 'operations'],
            ['Staff administration', 'Staff leave applications and independent reviews, restricted salary-planning calculations without bank disbursement.', 'leave'],
            ['School billing register', 'Restricted invoice drafts from reviewed fee proposals, separate issue review and manual bank-statement match records.', 'billing'],
            ['Institution support', 'Private account requests, replies, searchable ticket history and closure or reopening.', 'helpdesk'],
            ['School announcements', 'Draft notices, independent leadership publication, campus audience visibility and personal in-app read receipts.', 'announcements'],
            ['Private notification inbox', 'Institution-scoped in-app notifications for issued or revoked statements and staff leave decisions, with read, unread filtering and archiving. No external delivery.', 'inbox'],
        ];
    @endphp
    <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach($features as [$name, $description, $icon])
            <article class="edvora-surface p-6">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300"><x-nav-icon :name="$icon" class="h-6 w-6" /></span>
                <h3 class="mt-5 text-lg font-bold">{{ $name }}</h3>
                <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $description }}</p>
            </article>
        @endforeach
    </div>
</section>
<section class="edvora-surface p-7" aria-labelledby="release-caveat">
    <h2 id="release-caveat" class="text-lg font-bold">Development and release status</h2>
    <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-600 dark:text-slate-300">These features exist in Edvora's development branch and still require independent release acceptance. Installation on real school infrastructure, physical-device acceptance, privacy-policy approval, independent encrypted-backup restore and security sign-off are not yet complete. Live payment collection and external school notifications remain disabled. Some transfers are supported only for unlinked students with no guardian, enrollment, financial or issued academic statement records; other transfers remain blocked until access and records can be reconciled.</p>
    <a href="{{ route('public.security') }}" class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">Learn about the security model →</a>
</section>
@endsection
