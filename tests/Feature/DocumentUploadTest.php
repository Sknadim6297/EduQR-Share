<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_teacher_can_upload_a_pdf_to_private_generated_storage(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'lesson.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
        );

        $response = $this->actingAs($teacher)->post(route('documents.store'), [
            'title' => 'Lesson notes',
            'file' => $file,
        ]);

        $response->assertRedirect(route('documents.result', Document::query()->firstOrFail()));
        $this->assertDatabaseCount('documents', 1);
        $document = Document::query()->firstOrFail();
        $this->assertSame($teacher->id, $document->user_id);
        $this->assertSame('Lesson notes', $document->title);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertNotSame('lesson.pdf', basename($document->file_path));
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    public function test_guest_cannot_upload_a_document(): void
    {
        $response = $this->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('lesson.pdf', "%PDF-1.4\n%%EOF"),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_authenticated_teacher_can_open_document_history(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Your documents');
    }

    public function test_authenticated_teacher_can_upload_a_png_image(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->image('diagram.png', 64, 64),
        ])->assertRedirect(route('documents.result', Document::query()->firstOrFail()));

        $document = Document::query()->firstOrFail();
        $this->assertSame('image/png', $document->mime_type);
        $this->assertSame('diagram', $document->title);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    public function test_authenticated_teacher_can_upload_a_jpeg_image(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 64, 64),
        ])->assertRedirect(route('documents.result', Document::query()->firstOrFail()));

        $document = Document::query()->firstOrFail();
        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    public function test_client_filename_paths_are_reduced_to_safe_metadata(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('../secret.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertRedirect(route('documents.result', Document::query()->firstOrFail()));

        $document = Document::query()->firstOrFail();
        $this->assertSame('secret.pdf', $document->original_filename);
        $this->assertSame('secret', $document->title);
        $this->assertStringNotContainsString('..', $document->file_path);
    }

    public function test_upload_rejects_unsupported_and_malformed_files(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($teacher)->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('broken.pdf', 'not a pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_upload_rejects_files_over_the_configured_size_limit(): void
    {
        Storage::fake('local');
        config(['documents.max_upload_kilobytes' => 10]);

        $file = UploadedFile::fake()->createWithContent(
            'large.pdf',
            "%PDF-1.4\n".str_repeat('x', 11 * 1024)
        );

        $this->actingAs(User::factory()->create())->post(route('documents.store'), [
            'file' => $file,
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_file_is_removed_if_metadata_insert_fails(): void
    {
        Storage::fake('local');
        DB::statement("CREATE TRIGGER reject_document_insert BEFORE INSERT ON documents BEGIN SELECT RAISE(ABORT, 'test insert failure'); END");
        $file = UploadedFile::fake()->createWithContent('lesson.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs(User::factory()->create())->post(route('documents.store'), [
            'file' => $file,
        ])->assertSessionHasErrors('file');

        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }
}
