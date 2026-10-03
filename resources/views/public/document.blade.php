@extends('layouts.app')

@section('title', $document->title.' · Shared document')

@section('content')
    <main class="mx-auto max-w-6xl px-5 py-9 sm:px-8 sm:py-12">
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div class="min-w-0"><p class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700"><span class="h-2 w-2 rounded-full bg-indigo-600"></span> Shared with you</p><h1 class="mt-4 break-words font-display text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{{ $document->title }}</h1><p class="mt-2 break-all text-sm text-slate-600">{{ $document->original_filename }} <span class="px-1 text-slate-300">·</span> {{ number_format($document->file_size / 1048576, 2) }} MB</p></div><div class="flex shrink-0 flex-wrap gap-2">
            @if ($isPdf)
                <a class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('share.preview', $document->public_token) }}" target="_blank" rel="noopener">Open PDF <span class="ml-2" aria-hidden="true">↗</span></a>
                <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700" href="{{ route('share.download', $document->public_token) }}">Download PDF <span aria-hidden="true">↓</span></a>
            @else
                <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700" href="{{ route('share.download', $document->public_token) }}">Download image <span aria-hidden="true">↓</span></a>
            @endif
        </div></section>
        <section class="saas-public-preview mt-7 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 p-2 sm:mt-9 sm:p-4" aria-label="Document preview">
            @if ($isPdf)
                <iframe class="rounded-lg" src="{{ route('share.preview', $document->public_token) }}" title="Preview of {{ $document->title }}" loading="lazy" referrerpolicy="no-referrer"></iframe>
            @else
                <img class="rounded-lg" src="{{ route('share.preview', $document->public_token) }}" alt="{{ $document->title }}" loading="lazy" referrerpolicy="no-referrer">
            @endif
        </section>
        <p class="mt-4 text-center text-xs text-slate-500">Shared securely through School QR Share <span class="px-1 text-slate-300">·</span> No student account required</p>
    </main>
@endsection