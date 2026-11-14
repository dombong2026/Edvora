@extends('layouts.app')
@section('title', $institution->name . ' dashboard')
@section('content')
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Institution workspace</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $institution->name }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Academic administration overview · {{ $selectedCampus?->name ?? 'Authorized campuses' }}</p>
    </div>
    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">Active institution</span>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Campuses you can access</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ $campuses->count() }}</p>
        <p class="mt-2 text-xs text-slate-500">Scoped to your assigned permissions</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Active institution members</p>
        @if(in_array('administrator', $roleCodes, true) || in_array('director', $roleCodes, true))
            <p class="mt-4 text-4xl font-bold tracking-tight">{{ $memberCount }}</p>
        @else
            <p class="mt-4 text-xl font-bold">Restricted</p>
        @endif
        <p class="mt-2 text-xs text-slate-500">Visible to authorized institution leadership only</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Assigned account roles</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ count($roleCodes) }}</p>
        <p class="mt-2 text-xs text-slate-500">Granted by your institution</p>
    </article>
    <article class="edvora-surface p-6">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Classes I can access</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ $visibleClassCount }}</p>
        @if($canManageAcademics || count(array_intersect($roleCodes, ['teacher', 'professor', 'student', 'parent'])) > 0)
            <a href="{{ route('classrooms.index', $institution) }}" class="mt-2 inline-block text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Open class workspace</a>
        @else
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Not part of this role</p>
        @endif
    </article>
    <article class="edvora-surface p-6">
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Student records I can access</p>
        <p class="mt-4 text-4xl font-bold tracking-tight">{{ $visibleStudentCount }}</p>
        @if($canManageAcademics || count(array_intersect($roleCodes, ['student', 'parent'])) > 0)
            <a href="{{ route('students.index', $institution) }}" class="mt-2 inline-block text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400">Open student records</a>
        @else
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Not part of this role</p>
        @endif
    </article>
</div>

<section class="edvora-surface mt-6 p-6" aria-label="Available workspace actions">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold">Continue your work</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Only implemented, permission-checked workspaces are linked.</p>
        </div>
        <a href="{{ route('account.index') }}" class="edvora-secondary">My account</a>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @if($canInvite)
            <a href="{{ route('members.index', $institution) }}" class="edvora-list-link"><span>Members and roles</span><span aria-hidden="true">→</span></a>
            <a href="#invitations" class="edvora-list-link"><span>Invite institution member</span><span aria-hidden="true">→</span></a>
        @endif
        @if($canManageAcademics)
            <a href="{{ route('academic.index', $institution) }}" class="edvora-list-link"><span>Academic catalog</span><span aria-hidden="true">→</span></a>
            <a href="{{ route('students.index', $institution) }}" class="edvora-list-link"><span>Student registry</span><span aria-hidden="true">→</span></a>
            <a href="{{ route('classrooms.index', $institution) }}" class="edvora-list-link"><span>Manage classrooms</span><span aria-hidden="true">→</span></a>
        @elseif(count(array_intersect($roleCodes, ['teacher', 'professor', 'student', 'parent'])) > 0)
            <a href="{{ route('classrooms.index', $institution) }}" class="edvora-list-link"><span>My classes</span><span aria-hidden="true">→</span></a>
            @if(count(array_intersect($roleCodes, ['student', 'parent'])) > 0)
                <a href="{{ route('students.index', $institution) }}" class="edvora-list-link"><span>My linked student records</span><span aria-hidden="true">→</span></a>
            @endif
        @endif
        <a href="{{ route('mfa.setup') }}" class="edvora-list-link"><span>Account security</span><span aria-hidden="true">→</span></a>
    </div>
</section>

<div class="mt-6 grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold">My campuses</h2>
            <span class="text-xs text-slate-500">{{ $campuses->count() }} available</span>
        </div>
        <div class="mt-5 space-y-2">
            @forelse($campuses as $campus)
                <a class="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold hover:border-blue-300 hover:bg-blue-50 dark:border-slate-800 dark:hover:border-blue-900 dark:hover:bg-blue-950" href="{{ route('dashboard.campus', [$institution, $campus]) }}">
                    <span>{{ $campus->name }}</span>
                    <span aria-hidden="true" class="text-blue-600">→</span>
                </a>
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">No active campus assignments are available for this account.</p>
            @endforelse
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @php
            $roleGuidance = [
                'administrator' => 'Oversee academic catalogs, student registry verification and classes within your institution.',
                'director' => 'Review school academic operations, account invitations and student access with MFA.',
                'subdirector' => 'Coordinate your assigned school leadership responsibilities while awaiting independently approved administrative modules.',
                'manager' => 'Manage authorized class setup, academic years and verified student records.',
                'dean' => 'Coordinate academic years, subjects, classes and controlled school record workflows.',
                'supervisor' => 'Review your assigned campus access and verified account security.',
                'teacher' => 'Access only classes assigned to you, attendance, assessments and teaching schedules.',
                'professor' => 'Work with your assigned classes, assessments, attendance and approved schedules.',
                'student' => 'View your verified student record and your enrolled classes and published academic work.',
                'parent' => 'Follow only student records and classes linked through a verified guardian relationship.',
                'librarian' => 'Manage your account security and assigned campus access. Library circulation tools are not released yet.',
                'receptionist' => 'Use your secure institution account. Reception and visitor workflows remain a future milestone.',
                'cashier' => 'Use your secure institution account. Payment and billing tools are not enabled.',
                'salesperson' => 'Access your verified account and institution membership. Admissions CRM is not released yet.',
            ];
        @endphp
        <h2 class="text-base font-bold">{{ $selectedRole ? $selectedRole->label() . ' workspace' : 'My account responsibilities' }}</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $selectedRole ? ($roleGuidance[$selectedRole->value] ?? 'Use the actions your institution has assigned to this role.') : 'Select an authorized role to view its responsibilities. Each role uses the same layout and server-verified access boundaries.' }}</p>
        <div class="mt-5 flex flex-wrap gap-2">
            @foreach($roleCodes as $code)
                <a href="{{ route('dashboard.role', [$institution, $code]) }}" class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">{{ $code === 'subdirector' ? 'Deputy Director' : ucfirst($code) }}</a>
            @endforeach
        </div>
    </section>
</div>

@if($canInvite)
<section id="invitations" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold">Invite an institution member</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Send a private, 48-hour, one-use invitation. Only assigned roles and authorized campuses can be accessed.</p>
        </div>
        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">Administrator access</span>
    </div>
    <form method="POST" action="{{ route('invitations.store', $institution) }}" class="mt-6 grid gap-4 md:grid-cols-3">
        @csrf
        <div>
            <label class="mb-2 block text-sm font-semibold" for="invite-email">Email address</label>
            <input id="invite-email" name="email" type="email" autocomplete="off" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950" value="{{ old('email') }}">
            @error('email') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold" for="invite-role">Role</label>
            <select id="invite-role" name="role" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Select role</option>
                @foreach($inviteRoles as $role)
                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @error('role') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold" for="invite-campus">Campus</label>
            <select id="invite-campus" name="campus_id" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">Institution-wide leadership only</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('campus_id') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <p class="md:col-span-2 text-xs leading-6 text-slate-500 dark:text-slate-400">Teachers, students, parents and other non-leadership roles require a campus. Invitations are sent through the configured mail provider.</p>
        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Send invitation</button>
    </form>
</section>
@endif

@if($canInvite && $pendingInvitations->isNotEmpty())
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-base font-bold">Pending invitations</h2>
    <p class="mt-2 text-xs text-slate-500">Only authorized institution leadership may view and revoke invitation links.</p>
    <ul class="mt-4 divide-y divide-slate-200 dark:divide-slate-800">
        @foreach($pendingInvitations as $invitation)
            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $invitation->email }}</p>
                    <p class="mt-1 text-xs text-slate-500">Expires {{ $invitation->expires_at->format('d M Y H:i') }}</p>
                </div>
                <form method="POST" action="{{ route('invitations.revoke', [$institution, $invitation]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Revoke invitation</button>
                </form>
            </li>
        @endforeach
    </ul>
</section>
@endif
@endsection
