@extends('layouts.app')
@section('title', 'Security and privacy')
@section('content')
<section class="mb-10 max-w-4xl">
    <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Trust and access control</p>
    <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Built for accountable school operations</h1>
    <p class="mt-5 text-base leading-8 text-slate-600 dark:text-slate-300">Edvora is designed with explicit institution membership, campus boundaries, protected account controls and carefully separated review steps.</p>
</section>
<div class="grid gap-5 md:grid-cols-2">
    <article class="edvora-surface p-7">
        <h2 class="text-lg font-bold">Institution and campus isolation</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Authorizations are enforced on server routes and sensitive data queries. Platform administrator designation does not automatically provide access to student or school records.</p>
    </article>
    <article class="edvora-surface p-7">
        <h2 class="text-lg font-bold">Authenticator protection</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Staff performing privileged operations use authenticator-based MFA. Password changes and account membership are protected separately from public browsing.</p>
    </article>
    <article class="edvora-surface p-7">
        <h2 class="text-lg font-bold">Review and accountability</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">Academic corrections, selected fee proposals and school notices use independent approval steps. Security events record identifiers without logging private message text or passwords.</p>
    </article>
    <article class="edvora-surface p-7">
        <h2 class="text-lg font-bold">Controlled communications</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">School support requests stay in the institution’s private workspace. Draft announcements are not visible to ordinary accounts and external notifications remain disabled for these modules.</p>
    </article>
</div>
<section class="edvora-surface mt-7 p-7">
    <h2 class="text-lg font-bold">Additional acceptance still required</h2>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">This page describes the implemented development architecture, not independent certification or a final privacy policy. Before any production school uses Edvora, administrators must complete staging installation, backup/restore exercises, accessibility reviews, legal/privacy policy approval and security acceptance.</p>
    <div class="mt-5 flex flex-wrap gap-3">
        <a href="{{ route('public.help') }}" class="edvora-secondary">Security questions</a>
        @auth<a href="{{ route('mfa.setup') }}" class="edvora-primary">Manage my authentication</a>@else<a href="{{ route('login') }}" class="edvora-primary">Sign in</a>@endauth
    </div>
</section>
@endsection
