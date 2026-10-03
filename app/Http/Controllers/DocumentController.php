<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
use App\Models\Document;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:pdf,image'],
        ]);
        $search = trim($filters['search'] ?? '');
        $type = $filters['type'] ?? '';
        $documentsQuery = $request->user()->documents()
            ->select(['id', 'user_id', 'title', 'original_filename', 'mime_type', 'file_size', 'public_token', 'created_at']);

        if ($search !== '') {
            $documentsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('original_filename', 'like', '%'.$search.'%');
            });
        }

        if ($type === 'pdf') {
            $documentsQuery->where('mime_type', 'application/pdf');
        } elseif ($type === 'image') {
            $documentsQuery->whereIn('mime_type', ['image/jpeg', 'image/png']);
        }

        $documents = $documentsQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(config('documents.history_page_size'))
            ->withQueryString();
        $documentCount = $request->user()->documents()->count();

        return view('documents.index', compact('documents', 'documentCount', 'search', 'type'));
    }

    public function store(DocumentUploadRequest $request): RedirectResponse|JsonResponse
    {
        $file = $request->file('file');
        $disk = config('documents.storage_disk');
        $path = false;

        try {
            $path = $file->store('documents', $disk);

            if (! is_string($path) || $path === '') {
                throw new \RuntimeException('The document could not be stored.');
            }

            $originalFilename = $this->safeOriginalFilename($file);
            $title = trim((string) $request->validated('title'));
            $document = $request->user()->documents()->make([
                'title' => $title !== '' ? $title : mb_substr(pathinfo($originalFilename, PATHINFO_FILENAME), 0, 255),
                'original_filename' => $originalFilename,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $document->storage_disk = $disk;
            $document->file_path = $path;
            $document->saveOrFail();
        } catch (Throwable $exception) {
            if (is_string($path)) {
                $this->deleteStoredFile($disk, $path, 'upload_rollback');
            }

            Log::error('Document upload failed.', [
                'operation' => 'upload',
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'The upload could not be completed. Please try again.'], 500);
            }

            return back()->withErrors(['file' => 'The upload could not be completed. Please try again.']);
        }

        $redirect = route('documents.result', $document);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $redirect], 201);
        }

        return redirect()->to($redirect)->with('status', 'Document uploaded successfully.');
    }

    public function result(Request $request, int $document): View
    {
        $document = $this->ownedDocument($request, $document);

        return view('documents.result', [
            'document' => $document,
            'shareUrl' => $this->publicUrl($document),
        ]);
    }

    public function qr(Request $request, int $document): Response
    {
        $document = $this->ownedDocument($request, $document);

        return $this->qrResponse($document, false);
    }

    public function downloadQr(Request $request, int $document): Response
    {
        $document = $this->ownedDocument($request, $document);

        return $this->qrResponse($document, true);
    }

    public function update(DocumentUploadRequest $request, int $document): RedirectResponse|JsonResponse
    {
        $document = $this->ownedDocument($request, $document);
        $file = $request->file('file');
        $newDisk = config('documents.storage_disk');
        $newPath = false;

        try {
            $newPath = $file->store('documents', $newDisk);

            if (! is_string($newPath) || $newPath === '') {
                throw new \RuntimeException('The replacement file could not be stored.');
            }

            $oldDisk = $document->storage_disk;
            $oldPath = $document->file_path;
            $changed = DB::transaction(fn (): int => DB::table('documents')
                ->where('id', $document->id)
                ->where('user_id', $request->user()->id)
                ->where('storage_disk', $oldDisk)
                ->where('file_path', $oldPath)
                ->update([
                    'storage_disk' => $newDisk,
                    'file_path' => $newPath,
                    'original_filename' => $this->safeOriginalFilename($file),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'updated_at' => now(),
                ]));

            if ($changed !== 1) {
                $this->deleteStoredFile($newDisk, $newPath, 'replacement_conflict_cleanup');

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'This document changed during replacement. Refresh and try again.'], 409);
                }

                return back()->withErrors(['file' => 'This document changed during replacement. Refresh and try again.']);
            }
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                $this->deleteStoredFile($newDisk, $newPath, 'replacement_rollback');
            }

            Log::error('Document replacement failed.', [
                'operation' => 'replacement',
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'The replacement could not be completed. The existing document is unchanged.'], 500);
            }

            return back()->withErrors(['file' => 'The replacement could not be completed. The existing document is unchanged.']);
        }

        if (! $this->deleteStoredFile($oldDisk, $oldPath, 'replacement_old_file_cleanup')) {
            $request->session()->flash('warning', 'The document was replaced, but its previous file could not be cleaned up. Contact the administrator.');
        } else {
            $request->session()->flash('status', 'Document replaced. Its share link and QR code are unchanged.');
        }

        $redirect = route('documents.result', $document->id);

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect], 200)
            : redirect()->to($redirect);
    }

    public function destroy(Request $request, int $document): RedirectResponse|JsonResponse
    {
        try {
            [$disk, $path, $id] = DB::transaction(function () use ($request, $document): array {
                $record = $request->user()->documents()->whereKey($document)->lockForUpdate()->firstOrFail();
                $file = [$record->storage_disk, $record->file_path, $record->id];
                if (! $record->delete()) {
                    throw new \RuntimeException('Document metadata could not be deleted.');
                }

                return $file;
            });
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Document deletion failed.', [
                'operation' => 'delete',
                'document_id' => $document,
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'The document could not be deleted. Please try again.'], 500);
            }

            return back()->withErrors(['document' => 'The document could not be deleted. Please try again.']);
        }

        $cleaned = $this->deleteStoredFile($disk, $path, 'delete_file_cleanup');
        $message = $cleaned
            ? 'Document deleted. Its public link is no longer available.'
            : 'The public link is disabled, but file cleanup is incomplete. Contact the administrator.';

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('documents.index'), 'warning' => ! $cleaned], 200);
        }

        return redirect()->route('documents.index')->with($cleaned ? 'status' : 'warning', $message);
    }

    private function ownedDocument(Request $request, int $id): Document
    {
        return $request->user()->documents()->whereKey($id)->firstOrFail();
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', mb_scrub($filename, 'UTF-8')) ?? '';

        return mb_substr($filename !== '' ? $filename : 'document', 0, 255);
    }

    private function publicUrl(Document $document): string
    {
        return rtrim(config('app.url'), '/').route('share.show', $document->public_token, false);
    }

    private function qrResponse(Document $document, bool $download): Response
    {
        try {
            $qrCode = QrCode::create($this->publicUrl($document))
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
                ->setSize(config('documents.qr_size'))
                ->setMargin(config('documents.qr_margin'))
                ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->setForegroundColor(new Color(20, 44, 73))
                ->setBackgroundColor(new Color(255, 255, 255));
            $png = (new PngWriter)->write($qrCode)->getString();
        } catch (Throwable $exception) {
            Log::error('Document QR generation failed.', [
                'operation' => 'qr',
                'document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            abort(503, 'The QR code is temporarily unavailable.');
        }

        $headers = [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($download) {
            $headers['Content-Disposition'] = 'attachment; filename="schoolqr-document-'.$document->id.'.png"';
        }

        return response($png, 200, $headers);
    }

    private function deleteStoredFile(string $disk, string $path, string $operation): bool
    {
        try {
            $filesystem = Storage::disk($disk);

            if (! $filesystem->exists($path)) {
                return true;
            }

            if ($filesystem->delete($path)) {
                return true;
            }

            throw new \RuntimeException('Stored file cleanup failed.');
        } catch (Throwable $exception) {
            Log::error('Document file cleanup failed.', [
                'operation' => $operation,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
