@extends('layouts.app')
@section('title', $title)
@section('content')
<header class="mb-7 flex flex-wrap items-start justify-between gap-3">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Operational workspace</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $title }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">
            @switch($module)
                @case('library') Maintain your campus catalog, issue controlled loans and record returns. @break
                @case('cashier') Prepare and independently review fee proposals; payments and live billing remain disabled. @break
                @case('reception') Record campus visitor arrival and departure with server-controlled times. @break
                @case('admissions') Track campus enquiries through permitted stages without sending external messages. @break
                @case('operations') Record and complete campus administrative tasks with accountable status changes. @break
            @endswitch
        </p>
    </div>
    <a class="edvora-secondary" href="{{ route('dashboard.institution', $institution) }}">Institution dashboard</a>
</header>
<div class="mb-6 flex flex-wrap gap-2">
    @foreach(app(\App\Services\OperationalAccess::class)->modules(auth()->user(), $institution) as $availableModule)
        <a href="{{ route('workspaces.index', [$institution, $availableModule]) }}" class="{{ $module === $availableModule ? 'edvora-primary' : 'edvora-secondary' }}">
            {{ \App\Services\OperationalAccess::TITLES[$availableModule] }}
        </a>
    @endforeach
</div>
<section class="edvora-surface p-5 sm:p-6" aria-label="Workspace filters">
    <form action="{{ route('workspaces.index', [$institution, $module]) }}" method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] md:items-end">
        <div>
            <label for="workspace-campus" class="mb-2 block text-sm font-semibold">Campus</label>
            <select id="workspace-campus" name="campus_id" class="edvora-field" required>
                @foreach($campuses as $option)
                    <option value="{{ $option->id }}" @selected($campus->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="workspace-query" class="mb-2 block text-sm font-semibold">Search this campus</label>
            <input id="workspace-query" name="q" type="search" maxlength="100" placeholder="Find a record" value="{{ $query }}" class="edvora-field">
        </div>
        <button type="submit" class="edvora-primary">Apply filters</button>
    </form>
    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Showing only records for {{ $campus->name }} that this account may manage. {{ $records->total() }} matching records.</p>
</section>
<div class="mt-6 space-y-6">
    @include('dashboard.workspaces.partials.'.$module)
</div>
@endsection
