@extends('layouts.app')

@section('title', 'Local admin setup · School QR Share')

@section('content')
    <main class="mx-auto max-w-lg px-5 py-16">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-indigo-600">Local setup only</p>
            <h1 class="mt-2 font-display text-2xl font-bold tracking-tight text-slate-950">Create the default admin account</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">This runs the existing idempotent admin seeder. It is only available from localhost while <code>APP_ENV=local</code>. It will not change an existing account’s password.</p>
            <form class="mt-6" method="post" action="{{ route('local.admin-seed.store') }}">
                @csrf
                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700" type="submit">Run admin seeder</button>
            </form>
            <p class="mt-4 text-xs text-slate-500">Configured email: <span class="font-semibold text-slate-700">{{ config('default_admin.email') }}</span></p>
        </section>
    </main>
@endsection