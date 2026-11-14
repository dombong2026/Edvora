@extends('layouts.app')
@section('title', 'Account roles')
@section('content')
<header class="mb-8 max-w-4xl">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Roles & responsibilities</p>
    <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">One premium experience for every account</h1>
    <p class="mt-5 text-base leading-8 text-slate-600 dark:text-slate-300">All 15 role definitions share the same responsive navigation, theme preferences and account security. Institution membership and campus permissions govern each feature.</p>
</header>
@php
    $roleDetails = [
        'admin' => 'Platform configuration and account-security view. No automatic permission to school records.',
        'director' => 'Institution-wide leadership, academic operations, approved invitations and independent review.',
        'administrator' => 'Institution administration, staff roles and verified school workflows.',
        'subdirector' => 'Campus operations task management and authorized leadership coordination.',
        'manager' => 'Academic catalog operations and campus task tracking where formally assigned.',
        'dean' => 'Academic catalogs, classrooms and campus leadership tasks.',
        'supervisor' => 'Authorized campus task tracking and operational coordination.',
        'teacher' => 'Assigned classes, teaching schedules, attendance and approved assessments.',
        'professor' => 'Assigned classes, course assessments, attendance and teaching schedules.',
        'student' => 'Verified student records and personally enrolled classes only.',
        'parent' => 'Linked children’s approved student records through verified guardian access.',
        'librarian' => 'Campus library catalog, physical inventory and borrower circulation.',
        'cashier' => 'Draft fee proposals only; independent leadership review with no payment collection.',
        'receptionist' => 'Campus visitor arrival and departure records.',
        'salesperson' => 'Campus admissions enquiry pipeline without automatic emails or enrollment.',
    ];
@endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach(\App\Enums\AccountRole::cases() as $role)
        <article class="edvora-surface p-6">
            <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">Account role</p>
            <h2 class="mt-3 text-xl font-bold">{{ $role->label() }}</h2>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $roleDetails[$role->value] ?? 'Role access is limited to its approved institution assignments.' }}</p>
        </article>
    @endforeach
</div>
<section class="mt-8 rounded-2xl border border-blue-100 bg-blue-50/70 p-7 dark:border-blue-900 dark:bg-blue-950/30">
    <h2 class="text-lg font-bold">How are roles assigned?</h2>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">An authorized school officer verifies membership and assigns roles at the permitted school or campus scope. Roles do not automatically grant access to other institutions, classmates or private family records.</p>
    <a href="{{ route('public.help') }}" class="mt-4 inline-block text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Read account access help →</a>
</section>
@endsection
