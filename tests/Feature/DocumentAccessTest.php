<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_previews_and_downloads_a_pdf_without_authentication(): void
    {
        Storage::fake('local');
        [$document, $contents] = $this->createDocument('application/pdf', 'lesson.pdf', "%PDF-1.4\n%%EOF");
        $this->assertGuest();

        $this->get(route('share.show', $document->public_token))
            ->assertOk()
            ->assertSee($document->title)
            ->assertSee(route('share.preview', $document->public_token))
            ->assertSee('Open PDF')
            ->assertSee('Download PDF');

        $preview = $this->get(route('share.preview', $document->public_token));
        $preview->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Disposition', 'inline; filename=lesson.pdf');
        $this->assertInstanceOf(StreamedResponse::class, $preview->baseResponse);
        $this->assertSame($contents, $preview->streamedContent());

        $download = $this->get(route('share.download', $document->public_token));
        $download->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=lesson.pdf');
        $this->assertSame($contents, $download->streamedContent());

        $range = $this->withHeader('Range', 'bytes=0-4')->get(route('share.preview', $document->public_token));
        $range->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-4/'.strlen($contents))
            ->assertHeader('Content-Length', '5');
        $this->assertSame('%PDF-', $range->streamedContent());

        $this->withHeader('Range', 'bytes=500-600')->get(route('share.preview', $document->public_token))
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */'.strlen($contents));
    }

    public function test_public_page_previews_an_image(): void
    {
        Storage::fake('local');
        $image = UploadedFile::fake()->image('worksheet.png', 48, 48);
        [$document] = $this->createDocument('image/png', 'worksheet.png', $image->get());

        $this->get(route('share.show', $document->public_token))
            ->assertOk()
            ->assertSee('alt="'.$document->title.'"', false)
            ->assertSee('Download image');
        $this->get(route('share.preview', $document->public_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get(route('share.download', $document->public_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'attachment; filename=worksheet.png');

        $this->post(route('share.preview', $document->public_token))->assertMethodNotAllowed();
    }

    public function test_jpeg_preview_and_download_use_safe_original_name(): void
    {
        Storage::fake('local');
        $image = UploadedFile::fake()->image('class-photo.jpg', 48, 48);
        [$document] = $this->createDocument('image/jpeg', 'class-photo.jpg', $image->get());

        $this->get(route('share.preview', $document->public_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('share.download', $document->public_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'attachment; filename=class-photo.jpg');
    }

    public function test_invalid_token_and_missing_file_return_not_found(): void
    {
        Storage::fake('local');
        $this->get(route('share.show', str_repeat('a', 64)))
            ->assertNotFound()
            ->assertSee('Document not available');

        $document = $this->createDocumentRecord('application/pdf', 'missing.pdf', 'documents/missing.pdf');
        $this->get(route('share.show', $document->public_token))
            ->assertNotFound()
            ->assertSee('Document not available');
        $this->get(route('share.download', $document->public_token))->assertNotFound();
    }

    public function test_unsafe_paths_and_mime_signature_mismatches_are_rejected(): void
    {
        Storage::fake('local');
        $unsafe = $this->createDocumentRecord('application/pdf', 'unsafe.pdf', 'documents/../outside.pdf');
        $this->get(route('share.show', $unsafe->public_token))->assertNotFound();

        [$mismatched] = $this->createDocument('image/png', 'not-a-png.png', "%PDF-1.4\n%%EOF");
        $this->get(route('share.preview', $mismatched->public_token))
            ->assertStatus(415)
            ->assertSee('File cannot be previewed');
    }

    public function test_download_filename_strips_header_control_characters(): void
    {
        Storage::fake('local');
        [$document] = $this->createDocument('application/pdf', "lesson\r\nX-Evil: yes.pdf", "%PDF-1.4\n%%EOF");

        $response = $this->get(route('share.download', $document->public_token))->assertOk();
        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringContainsString('lessonX-Evil: yes.pdf', $disposition);
    }

    public function test_guest_cannot_access_teacher_management_routes_and_result_uses_public_token_url(): void
    {
        Storage::fake('local');
        [$document] = $this->createDocument('application/pdf', 'lesson.pdf', "%PDF-1.4\n%%EOF");

        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->get(route('documents.result', $document))->assertRedirect(route('login'));

        $this->actingAs($document->user)
            ->get(route('documents.result', $document))
            ->assertOk()
            ->assertSee(route('share.show', $document->public_token));
    }

    public function test_teacher_can_download_a_generated_png_qr_code(): void
    {
        Storage::fake('local');
        [$document] = $this->createDocument('application/pdf', 'lesson.pdf', "%PDF-1.4\n%%EOF");
        $teacher = $document->user;

        $sharePage = $this->actingAs($teacher)->get(route('documents.result', $document));
        $sharePage->assertOk()
            ->assertSee('data-share-url="'.route('share.show', $document->public_token).'"', false)
            ->assertSee('data-qr-url="'.route('documents.qr', $document).'"', false)
            ->assertSee('data-qr-download-url="'.route('documents.qr.download', $document).'"', false);

        $preview = $this->get(route('documents.qr', $document));
        $preview->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $preview->getContent());
        $this->assertSame(784, getimagesizefromstring($preview->getContent())[0]);

        $this->get(route('documents.qr.download', $document))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="schoolqr-document-'.$document->id.'.png"');
    }

    public function test_other_teacher_cannot_manage_a_document_by_sequential_id(): void
    {
        Storage::fake('local');
        [$document] = $this->createDocument('application/pdf', 'lesson.pdf', "%PDF-1.4\n%%EOF");
        $otherTeacher = User::factory()->create();
        $this->actingAs($otherTeacher);

        $this->get(route('documents.result', $document))->assertNotFound();
        $this->get(route('documents.qr', $document))->assertNotFound();
        $this->put(route('documents.update', $document), [
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertNotFound();
        $this->delete(route('documents.destroy', $document))->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'user_id' => $document->user_id]);
    }

    private function createDocument(string $mimeType, string $filename, string $contents): array
    {
        $path = 'documents/'.uniqid('stored-', true).'.bin';
        Storage::disk('local')->put($path, $contents);

        return [$this->createDocumentRecord($mimeType, $filename, $path, strlen($contents)), $contents];
    }

    private function createDocumentRecord(string $mimeType, string $filename, string $path, int $size = 100): Document
    {
        $user = User::factory()->create();
        $document = $user->documents()->make([
            'title' => 'Shared class handout',
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'file_size' => $size,
        ]);
        $document->storage_disk = 'local';
        $document->file_path = $path;
        $document->saveOrFail();

        return $document;
    }
}
