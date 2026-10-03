@extends('layouts.app')

@section('title', 'Documents · School QR Share')

@section('content')
	<main class="mx-auto max-w-6xl space-y-8">
		<header class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end">
			<div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teacher workspace</p><h1 class="mt-1 font-display text-2xl font-semibold text-slate-950">Documents</h1><p class="mt-1 text-sm text-slate-600">Upload files and share them with your students.</p></div>
			<a href="#upload" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">Upload document</a>
		</header>

		<section id="upload" class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6" aria-labelledby="upload-heading">
			<div class="mb-5"><h2 id="upload-heading" class="font-display text-lg font-semibold text-slate-900">Upload a document</h2><p class="mt-1 text-sm text-slate-600">PDF, JPG, JPEG, or PNG up to {{ number_format(config('documents.max_upload_kilobytes') / 1024) }} MB.</p></div>
			<form class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(260px,1fr)_auto] sm:items-end" data-upload-form method="post" action="{{ route('documents.store') }}" enctype="multipart/form-data" data-max-kb="{{ config('documents.max_upload_kilobytes') }}">
				@csrf
				<div><label class="mb-1.5 block text-sm font-medium text-slate-700" for="title">Title <span class="font-normal text-slate-500">(optional)</span></label><input class="min-h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" id="title" name="title" maxlength="255" placeholder="e.g. Biology worksheet" value="{{ old('title') }}"></div>
				<div><label class="mb-1.5 block text-sm font-medium text-slate-700" for="file">Choose a file</label><label data-drop-zone class="saas-drop-zone flex min-h-10 cursor-pointer items-center rounded-md border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 hover:border-indigo-400 hover:bg-slate-50" for="file"><input class="sr-only" id="file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-file-input><span data-file-summary hidden class="min-w-0 flex-1 truncate">Selected file</span><span data-drop-prompt>Browse PDF or image</span></label></div>
				<button class="inline-flex min-h-10 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60" type="submit" data-submit-button>Upload</button>
				<p class="text-sm text-red-700 sm:col-span-3" data-upload-feedback aria-live="polite"></p>
				<div class="h-1 overflow-hidden rounded-full bg-slate-100 sm:col-span-3" data-progress-wrap hidden role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="block h-full w-0 bg-indigo-600" data-progress-bar></span></div>
				@error('title')<p class="text-sm text-red-700 sm:col-span-3" role="alert">{{ $message }}</p>@enderror
				@error('file')<p class="text-sm text-red-700 sm:col-span-3" role="alert">{{ $message }}</p>@enderror
			</form>
		</section>

		<section id="library" class="scroll-mt-6" aria-labelledby="library-heading">
			<div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 id="library-heading" class="font-display text-lg font-semibold text-slate-900">Your documents</h2><p class="mt-1 text-sm text-slate-600">{{ number_format($documentCount) }} total</p></div></div>
			<form class="mt-4 flex flex-col gap-2 sm:flex-row" method="get" action="{{ route('documents.index') }}" role="search">
				<label class="sr-only" for="document-search">Search documents</label><input class="min-h-10 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" id="document-search" name="search" type="search" value="{{ $search }}" placeholder="Search by title or filename">
				<label class="sr-only" for="document-type">File type</label><select class="min-h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" id="document-type" name="type"><option value="">All types</option><option value="pdf" @selected($type === 'pdf')>PDF</option><option value="image" @selected($type === 'image')>Images</option></select>
				<button class="min-h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50" type="submit">Search</button>
				@if ($search !== '' || $type !== '')<a class="inline-flex min-h-10 items-center justify-center px-3 text-sm text-slate-600 hover:text-indigo-700" href="{{ route('documents.index') }}">Clear</a>@endif
			</form>

			@if ($documents->isEmpty())
				<div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center"><h3 class="font-medium text-slate-900">{{ $search !== '' || $type !== '' ? 'No matching documents' : 'No documents yet' }}</h3><p class="mt-1 text-sm text-slate-600">{{ $search !== '' || $type !== '' ? 'Try a different search or file type.' : 'Your uploads will appear here.' }}</p></div>
			@else
				<div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white">
					<div class="hidden grid-cols-[minmax(0,1fr)_110px_130px_auto] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-medium text-slate-500 md:grid"><span>Document</span><span>Format</span><span>Uploaded</span><span class="text-right">Actions</span></div>
					<div class="divide-y divide-slate-100">
						@foreach ($documents as $document)
							<article class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_110px_130px_auto] sm:items-center sm:gap-4">
								<div class="min-w-0"><h3 class="truncate text-sm font-medium text-slate-900">{{ $document->title }}</h3><p class="mt-0.5 truncate text-xs text-slate-500">{{ $document->original_filename }} · {{ number_format($document->file_size / 1048576, 2) }} MB</p></div>
								<span class="text-xs text-slate-600">{{ $document->mime_type === 'application/pdf' ? 'PDF' : 'Image' }}</span>
								<time class="text-xs text-slate-500" datetime="{{ $document->created_at->toIso8601String() }}">{{ $document->created_at->format('M j, Y') }}</time>
								<div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs font-medium"><a class="text-indigo-700 hover:text-indigo-900" href="{{ route('documents.result', $document) }}">Share / QR</a><a class="text-slate-600 hover:text-indigo-700" href="{{ route('share.download', $document->public_token) }}">Download</a>
									<details class="relative"><summary class="cursor-pointer list-none text-slate-600 hover:text-indigo-700">Replace</summary><form class="absolute right-0 z-20 mt-2 grid w-[min(300px,calc(100vw-40px))] gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-lg" data-upload-form method="post" action="{{ route('documents.update', $document) }}" enctype="multipart/form-data" data-max-kb="{{ config('documents.max_upload_kilobytes') }}">@csrf @method('PUT')<label class="text-xs font-medium text-slate-700" for="replace-{{ $document->id }}">Replacement file</label><input id="replace-{{ $document->id }}" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-file-input><span class="truncate text-xs text-slate-600" data-file-summary hidden></span><p class="text-xs text-red-700" data-upload-feedback aria-live="polite"></p><div class="h-1 overflow-hidden rounded bg-slate-100" data-progress-wrap hidden role="progressbar" aria-label="Replacement upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="block h-full w-0 bg-indigo-600" data-progress-bar></span></div><button class="min-h-9 rounded bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-700" type="submit" data-submit-button>Replace</button></form></details>
									<form method="post" action="{{ route('documents.destroy', $document) }}" data-confirm="Delete this document? The share link will stop working.">@csrf @method('DELETE')<button class="text-red-700 hover:text-red-900" type="submit">Delete</button></form>
								</div>
							</article>
						@endforeach
					</div>
				</div>
				<div class="mt-4">{{ $documents->links() }}</div>
			@endif
		</section>
	</main>
@endsection
