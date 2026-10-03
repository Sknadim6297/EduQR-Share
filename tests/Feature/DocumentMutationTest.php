<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DocumentMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_preserves_id_token_and_url_then_removes_old_file(): void
    {
        Storage::fake('local');
        [$teacher, $document, $oldPath] = $this->createStoredDocument();
        $id = $document->id;
        $token = $document->public_token;
        $createdAt = $document->created_at;

        $this->actingAs($teacher)->put(route('documents.update', $document), [
            'file' => UploadedFile::fake()->createWithContent('updated.pdf', "%PDF-1.7\n%%EOF"),
        ])->assertRedirect(route('documents.result', $document));

        $updated = $document->fresh();
        $this->assertSame($id, $updated->id);
        $this->assertSame($token, $updated->public_token);
        $this->assertSame(route('share.show', $token), route('share.show', $updated->public_token));
        $this->assertSame($createdAt->toDateTimeString(), $updated->created_at->toDateTimeString());
        $this->assertSame('updated.pdf', $updated->original_filename);
        $this->assertNotSame($oldPath, $updated->file_path);
        $this->assertTrue(Storage::disk('local')->exists($updated->file_path));
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
    }

    public function test_failed_database_replacement_keeps_old_file_and_removes_staged_file(): void
    {
        Storage::fake('local');
        [$teacher, $document, $oldPath] = $this->createStoredDocument();
        DB::statement("CREATE TRIGGER reject_document_replacement BEFORE UPDATE OF file_path ON documents BEGIN SELECT RAISE(ABORT, 'test update failure'); END");

        $this->actingAs($teacher)->put(route('documents.update', $document), [
            'file' => UploadedFile::fake()->createWithContent('updated.pdf', "%PDF-1.7\n%%EOF"),
        ])->assertSessionHasErrors('file');

        $this->assertSame($oldPath, $document->fresh()->file_path);
        $this->assertTrue(Storage::disk('local')->exists($oldPath));
        $this->assertCount(1, Storage::disk('local')->allFiles('documents'));
    }

    public function test_concurrent_replacement_conflict_returns_conflict_and_cleans_staged_file(): void
    {
        Storage::fake('local');
        [$teacher, $document, $oldPath] = $this->createStoredDocument();
        DB::statement('CREATE TRIGGER simulate_replacement_race BEFORE UPDATE OF file_path ON documents BEGIN SELECT RAISE(IGNORE); END');

        $this->actingAs($teacher)->withHeader('Accept', 'application/json')->put(route('documents.update', $document), [
            'file' => UploadedFile::fake()->createWithContent('raced.pdf', "%PDF-1.7\n%%EOF"),
        ])->assertConflict();

        $this->assertSame($oldPath, $document->fresh()->file_path);
        $this->assertTrue(Storage::disk('local')->exists($oldPath));
        $this->assertCount(1, Storage::disk('local')->allFiles('documents'));
    }

    public function test_delete_invalidates_public_link_and_removes_the_owned_file(): void
    {
        Storage::fake('local');
        [$teacher, $document, $path] = $this->createStoredDocument();
        $token = $document->public_token;

        $this->actingAs($teacher)->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->get(route('share.show', $token))->assertNotFound();
    }

    public function test_delete_reports_file_cleanup_failure_without_restoring_public_access(): void
    {
        Storage::fake('local');
        [$teacher, $document, $path] = $this->createStoredDocument();
        $realDisk = Storage::disk('local');
        $disk = Mockery::mock($realDisk)->makePartial();
        $disk->shouldReceive('exists')->with($path)->once()->andReturn(true);
        $disk->shouldReceive('delete')->with($path)->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($teacher)->delete(route('documents.destroy', $document))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertTrue($realDisk->exists($path));
    }

    private function createStoredDocument(): array
    {
        $teacher = User::factory()->create();
        $path = 'documents/original.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");
        $document = $teacher->documents()->make([
            'title' => 'Original handout',
            'original_filename' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 14,
        ]);
        $document->storage_disk = 'local';
        $document->file_path = $path;
        $document->saveOrFail();

        return [$teacher, $document, $path];
    }
}
