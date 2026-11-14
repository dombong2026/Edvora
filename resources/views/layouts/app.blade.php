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
            } catch {
                document.documentElement.classList.toggle('dark', matchMedia('(prefers-color-scheme: dark)').matches);
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/95">
        <div class="mx-auto flex h-16 max-w-[1560px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="Edvora home">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-600/20" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M6 13v5c4 3 8 3 12 0v-5M21 10v6"/></svg>
                </span>
                <span class="text-lg font-bold tracking-tight">Edvora<span class="text-blue-600">.</span></span>
            </a>
            <div class="flex items-center gap-2 sm:gap-4">
                <fieldset class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 text-xs font-semibold dark:border-slate-700 dark:bg-slate-900" aria-label="Appearance">
                    <legend class="sr-only">Appearance</legend>
                    <button type="button" data-theme-option="light" aria-pressed="false" class="rounded-lg px-2.5 py-1.5 hover:bg-slate-200 dark:hover:bg-slate-800">Light</button>
                    <button type="button" data-theme-option="dark" aria-pressed="false" class="rounded-lg px-2.5 py-1.5 hover:bg-slate-200 dark:hover:bg-slate-800">Dark</button>
                    <button type="button" data-theme-option="system" aria-pressed="false" class="rounded-lg px-2.5 py-1.5 hover:bg-slate-200 dark:hover:bg-slate-800">System</button>
                </fieldset>
                @auth
                    <span class="hidden max-w-40 truncate text-sm text-slate-600 sm:block dark:text-slate-300">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-[1560px] items-start gap-6 px-4 py-8 sm:px-6 lg:px-8">
        @auth
            <aside class="hidden w-60 shrink-0 space-y-6 lg:block">
                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="px-3 pb-2 pt-1 text-xs font-bold uppercase tracking-widest text-slate-400">Workspace</p>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-xl bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Dashboard
                    </a>
                    @if(isset($institution, $roleCodes))
                        <a href="{{ route('academic.index', $institution) }}" class="mt-2 block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Academic catalog</a>
                        <a href="{{ route('students.index', $institution) }}" class="mt-2 block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Students & guardians</a>
                        <a href="{{ route('classrooms.index', $institution) }}" class="mt-2 block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">My classrooms</a>
                        <p class="px-3 pb-2 pt-5 text-xs font-bold uppercase tracking-widest text-slate-400">My roles</p>
                        @foreach($roleCodes as $code)
                            <a href="{{ route('dashboard.role', [$institution, $code]) }}" class="block rounded-xl px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ ucfirst($code === 'subdirector' ? 'Deputy Director' : $code) }}</a>
                        @endforeach
                    @endif
                </div>
                <p class="px-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Edvora v{{ config('edvora.version') }} · Secured institution workspace</p>
            </aside>
        @endauth
        <main id="main" class="min-w-0 flex-1">
            @if(session('status'))
                <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    <footer class="mx-auto max-w-[1560px] border-t border-slate-200 px-6 py-6 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
        Edvora © {{ date('Y') }} Ton Samith · <a class="hover:text-blue-600" href="https://tonsamith.com" rel="noopener noreferrer">Author</a>
    </footer>
</body>
</html>
