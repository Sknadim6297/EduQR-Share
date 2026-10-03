<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PublicDocumentController extends Controller
{
    public function show(string $token): View
    {
        $document = $this->findDocument($token);
        $this->ensureFileExists($document);
        $mimeType = $this->safeMimeType($document);

        return view('public.document', [
            'document' => $document,
            'isPdf' => $mimeType === 'application/pdf',
        ]);
    }

    public function preview(string $token): StreamedResponse
    {
        $document = $this->findDocument($token);
        $this->ensureFileExists($document);
        $mimeType = $this->safeMimeType($document);

        return $this->fileResponse($document, $mimeType, false);
    }

    public function download(string $token): StreamedResponse
    {
        $document = $this->findDocument($token);
        $this->ensureFileExists($document);
        $mimeType = $this->safeMimeType($document);

        return $this->fileResponse($document, $mimeType, true);
    }

    private function findDocument(string $token): Document
    {
        return Document::query()
            ->select(['id', 'title', 'original_filename', 'storage_disk', 'file_path', 'mime_type', 'file_size', 'public_token'])
            ->where('public_token', $token)
            ->firstOrFail();
    }

    private function ensureFileExists(Document $document): void
    {
        try {
            if (! $this->isSafeStoragePath($document->file_path)
                || ! array_key_exists($document->storage_disk, config('filesystems.disks', []))) {
                abort(404);
            }

            if (! Storage::disk($document->storage_disk)->exists($document->file_path)) {
                abort(404);
            }
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Public document storage check failed.', [
                'operation' => 'public_read',
                'document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            abort(503, 'The document is temporarily unavailable.');
        }
    }

    private function safeMimeType(Document $document): string
    {
        $mimeType = match ($document->mime_type) {
            'application/pdf', 'image/jpeg', 'image/png' => $document->mime_type,
            default => abort(415),
        };

        try {
            /** @var FilesystemAdapter $filesystem */
            $filesystem = Storage::disk($document->storage_disk);
            $stream = $filesystem->readStream($document->file_path);

            if (! is_resource($stream)) {
                throw new \RuntimeException('The document stream could not be opened.');
            }

            try {
                $signature = fread($stream, 8);
            } finally {
                fclose($stream);
            }
        } catch (Throwable $exception) {
            Log::error('Public document signature check failed.', [
                'operation' => 'public_signature_check',
                'document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            abort(503, 'The document is temporarily unavailable.');
        }

        $signatureMatches = match ($mimeType) {
            'application/pdf' => str_starts_with($signature, '%PDF-'),
            'image/jpeg' => str_starts_with($signature, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($signature, "\x89PNG\r\n\x1A\n"),
        };

        if (! $signatureMatches) {
            abort(415, 'This document has an unsupported or inconsistent file type.');
        }

        return $mimeType;
    }

    private function isSafeStoragePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, 'documents/')
            && ! preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $normalized)
            && ! str_contains($normalized, "\0");
    }

    private function fileResponse(Document $document, string $mimeType, bool $download): StreamedResponse
    {
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        };
        try {
            /** @var FilesystemAdapter $filesystem */
            $filesystem = Storage::disk($document->storage_disk);
            $size = $filesystem->size($document->file_path);
            $range = $this->parseRange(request()->header('Range'), $size);

            if ($range === false) {
                return response()->stream(static fn () => null, 416, [
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => 'bytes */'.$size,
                    'Content-Length' => '0',
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            [$start, $end] = $range ?? [0, max(0, $size - 1)];
            $length = $size === 0 ? 0 : $end - $start + 1;
            $status = $range === null ? 200 : 206;
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $length,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ];

            if ($range !== null) {
                $headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;
            }

            $filename = $this->safeDownloadFilename($document, $extension);
            $response = response()->stream(function () use ($filesystem, $document, $start, $length): void {
                $stream = null;

                try {
                    $stream = $filesystem->readStream($document->file_path);

                    if (! is_resource($stream)) {
                        throw new \RuntimeException('The document stream could not be opened.');
                    }

                    $remainingToSkip = $start;
                    if ($remainingToSkip > 0 && stream_get_meta_data($stream)['seekable']) {
                        if (fseek($stream, $remainingToSkip) !== 0) {
                            throw new \RuntimeException('The document stream could not seek to the requested range.');
                        }
                        $remainingToSkip = 0;
                    }

                    while ($remainingToSkip > 0 && ! feof($stream)) {
                        $chunk = fread($stream, min(8192, $remainingToSkip));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        $remainingToSkip -= strlen($chunk);
                    }

                    if ($remainingToSkip > 0) {
                        throw new \RuntimeException('The document stream ended before the requested range.');
                    }

                    $remaining = $length;
                    while ($remaining > 0 && ! feof($stream)) {
                        $chunk = fread($stream, min(8192, $remaining));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        echo $chunk;
                        $remaining -= strlen($chunk);
                        flush();
                    }

                    if ($remaining > 0) {
                        throw new \RuntimeException('The document stream ended before delivery completed.');
                    }
                } catch (Throwable $exception) {
                    Log::error('Public document streaming failed.', [
                        'operation' => 'public_stream',
                        'document_id' => $document->id,
                        'exception' => $exception::class,
                    ]);

                    throw new \RuntimeException('The document could not be streamed.');
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, $status, $headers);

            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                $download ? 'attachment' : 'inline',
                $filename,
                Str::ascii($filename)
            ));

            return $response;
        } catch (Throwable $exception) {
            Log::error('Public document response could not be prepared.', [
                'operation' => $download ? 'download' : 'preview',
                'document_id' => $document->id,
                'exception' => $exception::class,
            ]);

            abort(503, 'The document is temporarily unavailable.');
        }
    }

    private function safeDownloadFilename(Document $document, string $extension): string
    {
        $originalName = basename(str_replace('\\', '/', $document->original_filename));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', mb_scrub($originalName, 'UTF-8')) ?? '';
        $name = trim(pathinfo($originalName, PATHINFO_FILENAME), ". \t\n\r\0\x0B");
        $name = $name !== '' ? $name : 'document';

        return mb_substr($name, 0, 200).'.'.$extension;
    }

    private function parseRange(?string $header, int $size): array|false|null
    {
        if ($header === null) {
            return null;
        }

        if ($size < 1 || preg_match('/\Abytes=(\d*)-(\d*)\z/i', trim($header), $matches) !== 1
            || ($matches[1] === '' && $matches[2] === '')) {
            return false;
        }

        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];
            if ($suffixLength < 1) {
                return false;
            }

            return [max(0, $size - $suffixLength), $size - 1];
        }

        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : (int) $matches[2];

        if ($start >= $size || $start > $end) {
            return false;
        }

        return [$start, min($end, $size - 1)];
    }
}
