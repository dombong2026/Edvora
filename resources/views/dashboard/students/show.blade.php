@extends('layouts.app')
@section('title', 'Student · ' . $profile->display_name)
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">{{ $institution->name }} · Authorized student record</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $profile->display_name }}</h1>
    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Student number: <span class="font-mono">{{ $profile->student_number }}</span> · Campus: {{ $profile->campus->name }}</p>
    <a class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline" href="{{ route('students.index', $institution) }}">Back to student workspace</a>
    <div class="mt-4 flex flex-wrap gap-3">
        <a class="edvora-secondary" href="{{ route('students.transcript.show', [$institution, $profile->id]) }}">Reviewed academic results</a>
        @if($canManage)
            <a class="edvora-primary" href="{{ route('students.lifecycle.index', [$institution, $profile->id]) }}">Student promotion / transfer / withdrawal</a>
        @endif
    </div>
</header>
<section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-bold">Student record overview</h2>
    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
        <div><dt class="text-xs text-slate-500">Institution</dt><dd class="mt-1 text-sm font-semibold">{{ $institution->name }}</dd></div>
        <div><dt class="text-xs text-slate-500">Campus</dt><dd class="mt-1 text-sm font-semibold">{{ $profile->campus->name }}</dd></div>
        <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-1 text-sm font-semibold">{{ ucfirst($profile->status) }}</dd></div>
        <div><dt class="text-xs text-slate-500">Account connection</dt><dd class="mt-1 text-sm font-semibold">{{ $profile->user_id ? 'Verified school account linked' : 'Not yet linked' }}</dd></div>
    </dl>
    <p class="mt-6 text-xs leading-6 text-slate-500 dark:text-slate-400">Independently published academic results are available through the reviewed results workspace. Official transcripts and full end-to-end transfer reconciliation remain future controlled milestones.</p>
</section>
@if($canManage)
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-base font-bold">Link student account</h2>
    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Only a verified student account belonging to this campus can be linked. Existing links cannot be replaced through self-service.</p>
    <form method="POST" action="{{ route('students.account.link', [$institution, $profile]) }}" class="mt-5 flex flex-wrap gap-3">
        @csrf
        <label class="min-w-[220px] flex-1 text-sm font-semibold" for="student-account-email">
            Student account email
            <input id="student-account-email" type="email" name="email" required maxlength="255" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
        </label>
        <button type="submit" class="self-end rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Verify and link</button>
    </form>
    @error('email') <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
</section>
@endif

@if($canVerifyGuardian)
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-base font-bold">Verified guardian access</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Grant access only after independently verifying the guardian's relationship and institutional authorization. Never use an unverified verbal request as proof.</p>
    <form method="POST" action="{{ route('students.guardians.verify', [$institution, $profile]) }}" class="mt-5 grid gap-3 sm:grid-cols-3">
        @csrf
        <div class="sm:col-span-2">
            <label for="guardian-email" class="mb-2 block text-sm font-semibold">Verified parent account email</label>
            <input id="guardian-email" name="email" type="email" maxlength="255" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
            @error('email') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="guardian-relationship" class="mb-2 block text-sm font-semibold">Relationship</label>
            <select id="guardian-relationship" name="relationship" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="parent">Parent</option>
                <option value="legal_guardian">Legal guardian</option>
            </select>
            @error('relationship') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="sm:col-span-3 justify-self-start rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">Verify guardian access</button>
    </form>
    <ul class="mt-6 divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($guardianLinks as $link)
            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                    <p class="text-sm font-semibold">{{ $link->guardian->name }} · {{ ucfirst(str_replace('_', ' ', $link->relationship)) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $link->revoked_at ? 'Revoked' : 'Verified' }} · {{ $link->verified_at->format('d M Y') }}</p>
                </div>
                @if(!$link->revoked_at)
                    <form method="POST" action="{{ route('students.guardians.revoke', [$institution, $profile, $link]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Revoke</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="py-5 text-sm text-slate-500">No verified guardian relationships on file.</li>
        @endforelse
    </ul>
</section>
@endif
@endsection
