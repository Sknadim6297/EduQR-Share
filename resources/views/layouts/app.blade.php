<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'School QR Share')</title>
    <meta name="theme-color" content="#4f46e5">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/saas.css', 'resources/js/app.js'])
</head>
<body class="saas-body min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    @if (auth()->check() && request()->routeIs('documents.*'))
        <div class="min-h-screen lg:grid lg:grid-cols-[256px_minmax(0,1fr)]">
            <aside id="teacher-sidebar" data-app-sidebar class="saas-sidebar fixed inset-y-0 left-0 z-50 flex w-[256px] -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
                <div class="flex h-[76px] items-center border-b border-slate-100 px-6">
                    <x-brand href="{{ route('documents.index') }}" />
                </div>
                <div class="px-4 pt-7">
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Workspace</p>
                    <nav class="mt-3 space-y-1" aria-label="Teacher navigation">
                        <a href="{{ route('documents.index') }}" @class(['flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors', 'bg-indigo-50 text-indigo-700' => request()->routeIs('documents.index'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => !request()->routeIs('documents.index')])>
                            <span class="grid h-5 w-5 place-items-center" aria-hidden="true">⌂</span> Overview
                        </a>
                        <a href="{{ route('documents.index') }}#upload" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">
                            <span class="grid h-5 w-5 place-items-center text-lg" aria-hidden="true">＋</span> Upload a document
                        </a>
                        <a href="{{ route('documents.index') }}#library" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">
                            <span class="grid h-5 w-5 place-items-center" aria-hidden="true">▤</span> My documents
                        </a>
                    </nav>
                </div>
                <div class="mt-auto border-t border-slate-100 p-4">
                    <div class="flex items-center gap-3 rounded-lg px-2 py-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="mt-1 flex min-h-10 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900" type="submit"><span aria-hidden="true">↪</span> Sign out</button>
                    </form>
                </div>
            </aside>
            <button type="button" data-sidebar-backdrop class="saas-sidebar-backdrop fixed inset-0 z-40 hidden bg-slate-950/40 lg:hidden" aria-label="Close navigation"></button>
            <div class="min-w-0">
                <header class="sticky top-0 z-30 flex h-[76px] items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-10">
                    <div class="flex items-center gap-3">
                        <button type="button" data-sidebar-toggle aria-controls="teacher-sidebar" aria-expanded="false" class="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-indigo-600 lg:hidden" aria-label="Open navigation">☰</button>
                        <div><p class="text-xs font-medium text-slate-500">Teacher workspace</p><p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p></div>
                    </div>
                    <a href="{{ route('documents.index') }}#upload" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><span aria-hidden="true">＋</span><span class="hidden sm:inline">New upload</span><span class="sm:hidden">Upload</span></a>
                </header>
                <div class="mx-auto w-full max-w-[1440px] px-4 py-7 sm:px-7 sm:py-9 lg:px-10">
                    <x-flash-messages />
                    @yield('content')
                </div>
                <footer class="border-t border-slate-200 px-4 py-5 text-center text-xs text-slate-500 sm:px-7 lg:px-10">School QR Share <span class="px-1 text-slate-300">·</span> Share learning, simply.</footer>
            </div>
        </div>
    @else
        <div class="min-h-screen">
            <header class="border-b border-slate-200/80 bg-white/90">
                <nav class="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-5 sm:px-8" aria-label="Main navigation">
                    <x-brand />
                    @if (request()->routeIs('home'))
                        <div class="hidden items-center gap-8 md:flex">
                            <a href="#how-it-works" class="text-sm font-medium text-slate-600 hover:text-indigo-700">How it works</a>
                        </div>
                    @endif
                    <div class="flex items-center gap-3">
                        @auth
                            @if (!request()->routeIs('share.*'))
                                <a class="text-sm font-semibold text-slate-600 hover:text-indigo-700" href="{{ route('documents.index') }}">Teacher workspace</a>
                            @endif
                        @else
                            @if (!request()->routeIs('login') && !request()->routeIs('share.*'))
                                <a class="inline-flex min-h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" href="{{ route('login') }}">Teacher sign in</a>
                            @endif
                        @endauth
                    </div>
                </nav>
            </header>
            <div>
                <div class="mx-auto max-w-7xl px-5 pt-5 sm:px-8"><x-flash-messages /></div>
                @yield('content')
            </div>
            <footer class="border-t border-slate-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-7 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <x-brand />
                    <p>Simple, secure document sharing for the classroom.</p>
                </div>
            </footer>
        </div>
    @endif
</body>
</html>