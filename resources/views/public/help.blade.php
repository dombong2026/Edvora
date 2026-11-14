@extends('layouts.app')
@section('title', 'Help & getting started')
@section('content')
<header class="mb-9 max-w-4xl">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Help center</p>
    <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Find your way around Edvora</h1>
    <p class="mt-5 text-base leading-8 text-slate-600 dark:text-slate-300">Guidance for account access, school assignments and using the shared dashboards. Institution-specific support requests are available inside your authorized school workspace.</p>
    <div class="mt-7 flex flex-wrap gap-3">
        @auth
            <a href="{{ route('dashboard') }}" class="edvora-primary">Open school dashboard</a>
            <a href="{{ route('account.index') }}" class="edvora-secondary">Manage my account</a>
        @else
            <a href="{{ route('login') }}" class="edvora-primary">Sign in to Edvora</a>
            <a href="{{ route('public.roles') }}" class="edvora-secondary">Explore roles</a>
        @endauth
    </div>
</header>
<section class="grid gap-5 lg:grid-cols-3" aria-label="Getting started steps">
    <article class="edvora-surface p-6">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Step 01</p>
        <h2 class="mt-3 text-lg font-bold">Get an invitation</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Your institution’s director or administrator assigns account roles and campus access through the approved invitation process.</p>
    </article>
    <article class="edvora-surface p-6">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Step 02</p>
        <h2 class="mt-3 text-lg font-bold">Secure your account</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Set up authenticator protection when required and open My Account to update your profile or change your password.</p>
    </article>
    <article class="edvora-surface p-6">
        <p class="text-xs font-bold uppercase tracking-widest text-blue-600">Step 03</p>
        <h2 class="mt-3 text-lg font-bold">Use authorized tools</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Your school dashboard shows functional modules you can access. Use Support requests for school-specific questions.</p>
    </article>
</section>
<section class="mt-10" aria-labelledby="faq-heading">
    <h2 id="faq-heading" class="text-2xl font-bold">Frequently asked questions</h2>
    @php
        $questions = [
            ['Why can I not see a school?', 'You need an active membership and an assigned institution role. Ask your school administrator to verify your account and campus.'],
            ['Where are my school support requests?', 'Sign in, choose your authorized institution dashboard and select Support requests in the sidebar. Requests stay private to you and authorized school leadership.'],
            ['How do I change light or dark mode?', 'Use Light, Dark or Auto in the page header. Your preference is stored on your current browser.'],
            ['Can a student view other students’ records?', 'No. Student and guardian academic records are scoped to verified accounts, approved relationships and authorized enrollment.'],
            ['Does the cashier area accept payments?', 'No. The current finance workspace supports draft fee proposals and independent review only.'],
            ['Can I create a new public account?', 'Public registration is disabled by default. New school members normally receive one-use institution invitations.'],
            ['Is Edvora ready for real school data?', 'No. Independent aaPanel installation, physical-device testing, school privacy approval and encrypted-backup recovery checks remain prerequisites.'],
        ];
    @endphp
    <div class="mt-6 space-y-3">
        @foreach($questions as [$question, $answer])
            <details class="edvora-surface group p-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-bold">
                    <span>{{ $question }}</span>
                    <span aria-hidden="true" class="text-blue-600 group-open:rotate-45">+</span>
                </summary>
                <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $answer }}</p>
            </details>
        @endforeach
    </div>
</section>
@endsection
