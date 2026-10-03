<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:'.config('documents.max_upload_kilobytes'),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('file');

            if (! $file instanceof UploadedFile || $validator->errors()->has('file')) {
                return;
            }

            if ($file->getMimeType() === 'application/pdf') {
                $stream = fopen($file->getRealPath(), 'rb');
                $signature = $stream === false ? false : fread($stream, 5);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($signature !== '%PDF-') {
                    $validator->errors()->add('file', 'The PDF file is malformed or unreadable.');
                }

                return;
            }

            try {
                $image = getimagesize($file->getRealPath());
            } catch (\Throwable) {
                $image = false;
            }

            $validImage = is_array($image)
                && (($file->getMimeType() === 'image/jpeg' && $image[2] === IMAGETYPE_JPEG)
                    || ($file->getMimeType() === 'image/png' && $image[2] === IMAGETYPE_PNG));

            if (! $validImage) {
                $validator->errors()->add('file', 'The image file is malformed or unreadable.');
            }
        }];
    }
}
