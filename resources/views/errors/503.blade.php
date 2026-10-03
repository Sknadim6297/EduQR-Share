@extends('layouts.app')

@section('title', 'Document temporarily unavailable · School QR Share')

@section('content')
    <main class="mx-auto max-w-2xl px-5 py-20 text-center sm:py-28">
        <p class="text-xs font-bold uppercase tracking-[0.12em] text-amber-700">Shared document</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-slate-950">Document temporarily unavailable</h1>
        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-600">The file could not be opened right now. Please try again in a moment or contact the teacher.</p>
        <a class="mt-7 inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('home') }}">Go to School QR Share</a>
    </main>
@endsection