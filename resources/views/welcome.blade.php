@extends('layouts.app')

@section('title', 'School QR Share · Upload and share documents')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-5 sm:px-8">
        <section class="grid items-center gap-10 py-14 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold text-indigo-700">Document sharing for the classroom</p>
                <h1 class="mt-4 font-display text-4xl font-bold leading-tight tracking-tight text-slate-950 sm:text-5xl">Upload, Generate QR &amp; Share Documents</h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-slate-600">Upload your PDF or image, generate a QR code, and let students access documents instantly.</p>
                <a class="mt-7 inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" href="{{ route('login') }}">Upload Document <span aria-hidden="true">→</span></a>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-7" aria-label="Upload document">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div><h2 class="font-display text-lg font-semibold text-slate-900">Share a classroom file</h2><p class="mt-1 text-sm text-slate-500">One upload, one link, one simple scan.</p></div>
                    <span class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-500">PDF · IMAGE</span>
                </div>
                <a href="{{ route('login') }}" class="mt-5 flex min-h-40 flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 text-center transition-colors hover:border-indigo-400 hover:bg-indigo-50/40 focus-visible:outline-2 focus-visible:outline-indigo-600">
                    <span class="grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white text-lg text-slate-600" aria-hidden="true">↑</span>
                    <span class="mt-3 text-sm font-semibold text-slate-800">Sign in to upload a document</span>
                    <span class="mt-1 text-xs text-slate-500">PDF, JPG, JPEG, or PNG up to 20 MB</span>
                </a>
                <p class="mt-4 text-xs text-slate-500">Files are stored privately. Students can open a shared link without an account.</p>
            </section>
        </section>

        <section id="how-it-works" class="border-t border-slate-200 py-9 sm:py-11" aria-labelledby="workflow-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Simple sharing</p><h2 id="workflow-heading" class="mt-1 font-display text-xl font-semibold text-slate-900">From upload to student access</h2></div>
                <p class="text-sm text-slate-500">No student sign-in required.</p>
            </div>
            <ol class="mt-6 grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-3 sm:gap-8">
                <li class="flex gap-3"><span class="text-sm font-semibold text-indigo-700">01</span><div><h3 class="text-sm font-semibold text-slate-900">Upload a file</h3><p class="mt-1 text-sm text-slate-500">Choose a PDF or image.</p></div></li>
                <li class="flex gap-3"><span class="text-sm font-semibold text-indigo-700">02</span><div><h3 class="text-sm font-semibold text-slate-900">Share the QR code</h3><p class="mt-1 text-sm text-slate-500">Copy the link or download the code.</p></div></li>
                <li class="flex gap-3"><span class="text-sm font-semibold text-indigo-700">03</span><div><h3 class="text-sm font-semibold text-slate-900">Students open it</h3><p class="mt-1 text-sm text-slate-500">Preview or download on any device.</p></div></li>
            </ol>
        </section>
    </main>
@endsection