<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Edvora') · Edvora</title>
    <script>
        (function () {
            try {
                const mode = localStorage.getItem('edvora.theme') || 'system';
                const dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.dataset.sidebarCollapsed = localStorage.getItem('edvora.sidebar-collapsed') === 'true' ? 'true' : 'false';
            } catch {
                document.documentElement.classList.toggle('dark', matchMedia('(prefers-color-scheme: dark)').matches);
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-white">Skip to content</a>
    <header class="sticky top-0 z-40 border-b border-slate-200/85 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/95">
        <div class="mx-auto flex h-16 max-w-[1600px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                @auth
                    <button type="button" data-sidebar-toggle aria-controls="edvora-sidebar" aria-expanded="false" aria-label="Show or hide navigation" class="edvora-icon-button" title="Show or hide menu">
                        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                @endauth
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="Edvora home">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-600/20" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M6 13v5c4 3 8 3 12 0v-5M21 10v6"/></svg>
                    </span>
                    <span class="text-lg font-extrabold tracking-tight">Edvora<span class="text-blue-600 dark:text-blue-400">.</span></span>
                </a>
            </div>
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <fieldset class="flex items-center gap-0.5 rounded-xl border border-slate-200 bg-slate-100 p-1 text-[11px] font-semibold dark:border-slate-700 dark:bg-slate-900" aria-label="Appearance">
                    <legend class="sr-only">Appearance</legend>
                    <button type="button" data-theme-option="light" aria-pressed="false" class="rounded-lg px-2 py-1.5 hover:bg-slate-200 dark:hover:bg-slate-800">Light</button>
                    <button type="button" data-theme-option="dark" aria-pressed="false" class="rounded-lg px-2 py-1.5 hover:bg-slate-200 dark:hover:bg-slate-800">Dark</button>
                    <button type="button" data-theme-option="system" aria-pressed="false" class="rounded-lg px-2 py-1.5 hover:bg-slate-200 dark:hover:bg-slate-800">Auto</button>
                </fieldset>
                @auth
                    <details class="relative" data-account-menu>
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800" aria-label="Account menu">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="hidden max-w-32 truncate sm:inline">{{ auth()->user()->name }}</span>
                            <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <a href="{{ route('dashboard') }}" class="edvora-menu-item">Dashboard</a>
                            <a href="{{ route('account.index') }}" class="edvora-menu-item">My account</a>
                            <a href="{{ route('mfa.setup') }}" class="edvora-menu-item">Account security</a>
                            @if(auth()->user()->isPlatformAdmin())
                                <a href="{{ route('dashboard.platform') }}" class="edvora-menu-item">Platform console</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-slate-100 pt-1 dark:border-slate-800">
                                @csrf
                                <button type="submit" class="edvora-menu-item w-full text-left">Sign out</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" class="edvora-primary">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    @auth
        @php
            $navInstitution = isset($institution) ? $institution : null;
            $navRoles = $navInstitution ? app(\App\Services\InstitutionAccess::class)->roleCodes(auth()->user(), $navInstitution) : [];
            $navAcademic = $navInstitution && app(\App\Services\AcademicCatalogService::class)->canManage(auth()->user(), $navInstitution);
            $navStudent = $navAcademic || count(array_intersect($navRoles, ['student', 'parent'])) > 0;
            $navClasses = $navAcademic || count(array_intersect($navRoles, ['teacher', 'professor', 'student', 'parent'])) > 0;
            $navMembers = $navInstitution && app(\App\Services\RoleAssignmentService::class)->canManage(auth()->user(), $navInstitution);
        @endphp
        <button type="button" data-sidebar-overlay class="edvora-sidebar-overlay" aria-label="Close navigation" tabindex="-1"></button>
    @endauth
    <div class="{{ auth()->check() ? 'edvora-workspace' : 'mx-auto max-w-[1600px]' }} px-4 py-7 sm:px-6 lg:px-8">
        @auth
            <aside class="edvora-sidebar" id="edvora-sidebar" aria-label="Main navigation">
                <nav class="edvora-surface space-y-1 p-3" aria-label="Account workspaces">
                    <p class="edvora-nav-heading">Workspace</p>
                    <a href="{{ route('dashboard') }}" class="edvora-nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                        <span class="edvora-nav-glyph"><x-nav-icon name="dashboard" /></span><span>Dashboard</span>
                    </a>
                    <a href="{{ route('account.index') }}" class="edvora-nav-link {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
                        <span class="edvora-nav-glyph"><x-nav-icon name="account" /></span><span>My account</span>
                    </a>
                    <a href="{{ route('mfa.setup') }}" class="edvora-nav-link {{ request()->routeIs('mfa.*') ? 'is-active' : '' }}">
                        <span class="edvora-nav-glyph"><x-nav-icon name="security" /></span><span>Security</span>
                    </a>
                    @if(auth()->user()->isPlatformAdmin())
                        <a href="{{ route('dashboard.platform') }}" class="edvora-nav-link {{ request()->routeIs('dashboard.platform') ? 'is-active' : '' }}">
                            <span class="edvora-nav-glyph"><x-nav-icon name="platform" /></span><span>Platform console</span>
                        </a>
                    @endif
                    @if($navInstitution && $navRoles !== [])
                        <p class="edvora-nav-heading pt-5">Institution</p>
                        <a href="{{ route('dashboard.institution', $navInstitution) }}" class="edvora-nav-link {{ request()->routeIs('dashboard.institution', 'dashboard.campus') ? 'is-active' : '' }}">
                            <span class="edvora-nav-glyph"><x-nav-icon name="institution" /></span><span class="truncate">{{ $navInstitution->name }}</span>
                        </a>
                        @if($navMembers)
                            <a href="{{ route('members.index', $navInstitution) }}" class="edvora-nav-link {{ request()->routeIs('members.*') ? 'is-active' : '' }}"><span class="edvora-nav-glyph"><x-nav-icon name="members" /></span><span>Members and roles</span></a>
                        @endif
                        @if($navAcademic)
                            <a href="{{ route('academic.index', $navInstitution) }}" class="edvora-nav-link {{ request()->routeIs('academic.*') ? 'is-active' : '' }}"><span class="edvora-nav-glyph"><x-nav-icon name="academic" /></span><span>Academic catalog</span></a>
                        @endif
                        @if($navStudent)
                            <a href="{{ route('students.index', $navInstitution) }}" class="edvora-nav-link {{ request()->routeIs('students.*') ? 'is-active' : '' }}"><span class="edvora-nav-glyph"><x-nav-icon name="students" /></span><span>Student records</span></a>
                        @endif
                        @if($navClasses)
                            <a href="{{ route('classrooms.index', $navInstitution) }}" class="edvora-nav-link {{ request()->routeIs('classrooms.*', 'attendance.*', 'assessments.*') ? 'is-active' : '' }}"><span class="edvora-nav-glyph"><x-nav-icon name="classrooms" /></span><span>Classrooms</span></a>
                        @endif
                        <p class="edvora-nav-heading pt-5">Assigned roles</p>
                        @foreach($navRoles as $code)
                            @php $roleLabel = \App\Enums\AccountRole::tryFrom($code)?->label() ?? ucfirst($code); @endphp
                            <a href="{{ route('dashboard.role', [$navInstitution, $code]) }}" class="edvora-nav-link {{ request()->routeIs('dashboard.role') && request()->route('role') === $code ? 'is-active' : '' }}">
                                <span class="edvora-nav-glyph"><x-nav-icon name="role" /></span><span>{{ $roleLabel }}</span>
                            </a>
                        @endforeach
                    @endif
                </nav>
                <p class="mt-5 px-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Edvora v{{ config('edvora.version') }} · Secure account workspace</p>
            </aside>
        @endauth
        <main id="main" class="min-w-0 w-full pb-8">
            @if(session('status'))
                <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    <footer class="mx-auto max-w-[1600px] border-t border-slate-200 px-6 py-6 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
        Edvora © {{ date('Y') }} Ton Samith · <a class="hover:text-blue-600 dark:hover:text-blue-400" href="https://tonsamith.com" rel="noopener noreferrer">Author</a>
    </footer>
</body>
</html>
