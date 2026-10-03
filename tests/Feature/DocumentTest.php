<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_can_be_created_with_valid_metadata_and_owner(): void
    {
        $user = User::factory()->create();
        $document = $this->createDocument($user);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $user->id,
            'title' => 'Course notes',
            'original_filename' => 'notes.pdf',
            'storage_disk' => 'private',
            'file_path' => 'documents/notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);
        $this->assertSame(64, strlen($document->public_token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $document->public_token);
        $this->assertSame($user->id, $document->user->id);
        $this->assertTrue($user->documents()->whereKey($document->id)->exists());
        $this->assertIsInt($document->file_size);

        $publicToken = $document->public_token;
        $document->title = 'Updated course notes';
        $document->save();

        $this->assertSame($publicToken, $document->fresh()->public_token);
    }

    public function test_required_document_fields_are_enforced_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('documents')->insert([
            'user_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_public_token_must_be_unique_in_the_database(): void
    {
        $document = $this->createDocument(User::factory()->create());

        $this->expectException(QueryException::class);

        DB::table('documents')->insert([
            'user_id' => $document->user_id,
            'title' => 'Duplicate token',
            'original_filename' => 'duplicate.pdf',
            'storage_disk' => 'private',
            'file_path' => 'documents/duplicate.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'public_token' => $document->public_token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sensitive_fields_are_not_mass_assignable(): void
    {
        $document = new Document([
            'title' => 'Course notes',
            'original_filename' => 'notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => '2048',
            'user_id' => 99,
            'public_token' => 'attacker-controlled',
            'storage_disk' => 'public',
            'file_path' => 'public/attacker.pdf',
        ]);

        $this->assertNull($document->user_id);
        $this->assertNull($document->public_token);
        $this->assertNull($document->storage_disk);
        $this->assertNull($document->file_path);
        $this->assertSame(2048, $document->file_size);
    }

    public function test_document_can_be_found_by_public_token_and_invalid_tokens_return_no_match(): void
    {
        $document = $this->createDocument(User::factory()->create());

        $this->assertSame($document->id, Document::query()
            ->where('public_token', $document->public_token)
            ->firstOrFail()->id);
        $this->assertNull(Document::query()->where('public_token', 'invalid-token')->first());
    }

    public function test_owner_document_listing_is_scoped_and_deterministically_ordered(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $older = $this->createDocument($owner);
        $firstAtSameTime = $this->createDocument($owner);
        $secondAtSameTime = $this->createDocument($owner);
        $other = $this->createDocument($otherUser);
        $sameTimestamp = now()->subMinute();

        DB::table('documents')->whereIn('id', [$firstAtSameTime->id, $secondAtSameTime->id])
            ->update(['created_at' => $sameTimestamp]);
        DB::table('documents')->where('id', $older->id)
            ->update(['created_at' => $sameTimestamp->copy()->subMinute()]);
        DB::table('documents')->where('id', $other->id)->update(['created_at' => now()]);

        $listedIds = $owner->documents()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $this->assertSame([
            max($firstAtSameTime->id, $secondAtSameTime->id),
            min($firstAtSameTime->id, $secondAtSameTime->id),
            $older->id,
        ], $listedIds);
    }

    public function test_document_history_search_and_type_filters_remain_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $matchingPdf = $this->createDocument($owner);
        $matchingPdf->title = 'Algebra revision';
        $matchingPdf->save();
        $matchingImage = $this->createDocument($owner);
        $matchingImage->title = 'Algebra diagram';
        $matchingImage->mime_type = 'image/png';
        $matchingImage->save();
        $otherDocument = $this->createDocument($otherOwner);
        $otherDocument->title = 'Algebra private';
        $otherDocument->save();

        $response = $this->actingAs($owner)->get(route('documents.index', [
            'search' => 'Algebra',
            'type' => 'pdf',
        ]));

        $response->assertOk()
            ->assertSee('Algebra revision')
            ->assertDontSee('Algebra diagram')
            ->assertDontSee('Algebra private')
            ->assertViewHas('documentCount', 2);
    }

    public function test_token_and_owner_queries_use_their_expected_sqlite_indexes(): void
    {
        $user = User::factory()->create();
        $document = $this->createDocument($user);
        $tokenQuery = Document::query()->where('public_token', $document->public_token);
        $ownerQuery = $user->documents()->orderByDesc('created_at')->orderByDesc('id');
        $primaryKeyQuery = Document::query()->whereKey($document->id);

        $tokenPlan = DB::select('EXPLAIN QUERY PLAN '.$tokenQuery->toSql(), $tokenQuery->getBindings());
        $ownerPlan = DB::select('EXPLAIN QUERY PLAN '.$ownerQuery->toSql(), $ownerQuery->getBindings());
        $primaryKeyPlan = DB::select('EXPLAIN QUERY PLAN '.$primaryKeyQuery->toSql(), $primaryKeyQuery->getBindings());

        $this->assertStringContainsString('public_token', collect($tokenPlan)->pluck('detail')->implode(' '));
        $this->assertStringContainsString('documents_user_recent_index', collect($ownerPlan)->pluck('detail')->implode(' '));
        $this->assertStringContainsString('INTEGER PRIMARY KEY', collect($primaryKeyPlan)->pluck('detail')->implode(' '));
    }

    private function createDocument(User $user): Document
    {
        $document = new Document([
            'title' => 'Course notes',
            'original_filename' => 'notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);
        $document->storage_disk = 'private';
        $document->file_path = 'documents/notes.pdf';
        $document->user()->associate($user);
        $document->save();

        return $document;
    }
}
