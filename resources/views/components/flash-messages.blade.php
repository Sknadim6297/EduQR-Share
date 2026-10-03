@if (session('status') || session('warning'))
    <div @class(['mb-6 rounded-lg border px-4 py-3 text-sm', 'border-emerald-200 bg-emerald-50 text-emerald-800' => !session('warning'), 'border-amber-200 bg-amber-50 text-amber-900' => session('warning')]) role="status">
        {{ session('warning') ?? session('status') }}
    </div>
@endif

@if (isset($errors) && $errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif