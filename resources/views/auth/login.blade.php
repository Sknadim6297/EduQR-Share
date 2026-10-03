@extends('layouts.app')

@section('title', 'Teacher sign in · School QR Share')

@section('content')
    <main class="mx-auto grid min-h-[calc(100vh-145px)] max-w-7xl items-center gap-10 px-5 py-10 sm:px-8 lg:grid-cols-[1fr_440px] lg:gap-16 lg:py-16">
        <section class="hidden max-w-2xl lg:block">
            <p class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700"><span class="h-2 w-2 rounded-full bg-indigo-600"></span> Teacher workspace</p>
            <p class="mt-6 font-display text-5xl font-extrabold leading-tight tracking-tight text-slate-950">A calmer way to<br><span class="text-indigo-600">share classroom work.</span></p>
            <p class="mt-5 max-w-lg text-base leading-7 text-slate-600">Your documents, links, and QR codes in one place. Students open shared materials without creating an account.</p>
            <div class="mt-9 flex items-center gap-4 border-t border-slate-200 pt-6"><span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-100 text-xl text-indigo-700" aria-hidden="true">▧</span><p class="text-sm font-medium text-slate-700">Upload privately <span class="px-1 text-slate-300">·</span> Share simply</p></div>
        </section>
        <section class="mx-auto w-full max-w-[440px] rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-indigo-600">Welcome back</p>
            <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-slate-950">Sign in to your workspace</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Manage your documents and share them with your class.</p>
            <form method="post" action="{{ route('login.store') }}" class="mt-7 space-y-5" data-login-form>
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="email">School email</label>
                    <input class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus @error('email') aria-describedby="email-error" @enderror placeholder="you@school.edu">
                    @error('email')<p id="email-error" class="mt-1.5 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <div class="mb-1.5 flex items-center justify-between"><label class="block text-sm font-semibold text-slate-700" for="password">Password</label></div>
                    <div class="relative"><input class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 pr-16 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100" id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-describedby="password-error" @enderror>
                        <button class="absolute inset-y-0 right-0 rounded-r-lg px-3 text-xs font-semibold text-slate-500 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-600" type="button" data-password-toggle aria-pressed="false">Show</button>
                    </div>
                    @error('password')<p id="password-error" class="mt-1.5 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <button class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" type="submit" data-login-submit><span data-login-label>Sign in</span><span aria-hidden="true">→</span></button>
            </form>
            <div class="mt-6 border-t border-slate-100 pt-5"><p class="text-xs leading-5 text-slate-500">Students don't need to sign in. Open the document link shared by your teacher.</p></div>
        </section>
    </main>
@endsection