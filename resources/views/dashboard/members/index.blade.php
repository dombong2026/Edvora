@extends('layouts.app')
@section('title', $institution->name . ' members')
@section('content')
<header class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-600 dark:text-blue-400">Institution administration</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Members and roles</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">Search active members and grant approved institution or campus roles. Every grant is access-checked and recorded.</p>
    </div>
    <a class="edvora-secondary" href="{{ route('dashboard.institution', $institution) }}">Return to dashboard</a>
</header>
<section class="edvora-surface overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-800">
        <h2 class="text-lg font-bold">Active institution members</h2>
        <form method="GET" action="{{ route('members.index', $institution) }}" class="flex flex-wrap gap-2">
            <label for="member-search" class="sr-only">Search members by name or email</label>
            <input id="member-search" name="q" type="search" maxlength="100" value="{{ $search }}" placeholder="Find name or email" class="edvora-field w-56">
            <button class="edvora-primary" type="submit">Search</button>
            @if($search !== '') <a class="edvora-secondary" href="{{ route('members.index', $institution) }}">Clear</a> @endif
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[680px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/40">
                <tr><th scope="col" class="px-6 py-4">Member</th><th scope="col" class="px-6 py-4">Assigned roles</th><th scope="col" class="px-6 py-4">Campus scope</th><th scope="col" class="px-6 py-4">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($memberships as $membership)
                    <tr>
                        <td class="px-6 py-4"><p class="font-bold">{{ $membership->user->name }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->user->email }}</p></td>
                        <td class="px-6 py-4">
                            @forelse($assignments->get($membership->user_id, collect()) as $assignment)
                                <span class="mb-1 mr-1 inline-block rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">{{ \App\Enums\AccountRole::tryFrom($assignment->role->code)?->label() ?? ucfirst($assignment->role->code) }}</span>
                            @empty
                                <span class="text-xs text-slate-500 dark:text-slate-400">Not assigned</span>
                            @endforelse
                        </td>
                        <td class="px-6 py-4">
                            @forelse($assignments->get($membership->user_id, collect()) as $assignment)
                                <span class="mb-1 block text-xs text-slate-500 dark:text-slate-400">{{ $assignment->campus?->name ?? 'Institution-wide' }}</span>
                            @empty
                                <span class="text-xs text-slate-500">—</span>
                            @endforelse
                        </td>
                        <td class="px-6 py-4"><a href="#assign-member-role" data-select-member="{{ $membership->user_id }}" class="text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Assign role</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">No active members match this search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($memberships->hasPages())
        <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $memberships->links() }}</div>
    @endif
</section>
<section id="assign-member-role" class="edvora-surface mt-6 p-6">
    <h2 class="text-lg font-bold">Grant an additional role</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Only active institution members are eligible. Classroom and family roles require a campus. A director role can only be assigned by an existing director.</p>
    <form method="POST" action="{{ route('members.grant', $institution) }}" class="mt-6 grid gap-4 lg:grid-cols-4">
        @csrf
        <div>
            <label for="assign-user" class="mb-2 block text-sm font-semibold">Active member</label>
            <select id="assign-user" name="user_id" required class="edvora-field">
                <option value="">Select from this page</option>
                @foreach($memberships as $membership)
                    <option value="{{ $membership->user_id }}" @selected((string)old('user_id') === (string)$membership->user_id)>{{ $membership->user->name }} ({{ $membership->user->email }})</option>
                @endforeach
            </select>
            @error('user_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="assign-role" class="mb-2 block text-sm font-semibold">Role</label>
            <select id="assign-role" name="role" required class="edvora-field">
                <option value="">Select a role</option>
                @foreach($availableRoles as $role)
                    @if($role !== \App\Enums\AccountRole::Director || $canGrantDirector)
                        <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                    @endif
                @endforeach
            </select>
            @error('role')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="assign-campus" class="mb-2 block text-sm font-semibold">Campus scope</label>
            <select id="assign-campus" name="campus_id" class="edvora-field">
                <option value="">Institution-wide leadership only</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string)old('campus_id') === (string)$campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('campus_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end"><button type="submit" class="edvora-primary w-full">Grant role</button></div>
    </form>
</section>
@endsection
