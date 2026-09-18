<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\KnowledgeCollection;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSegment;
use App\Services\EmbeddingService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_knowledge_collection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/knowledge', [
                'name'        => 'Test Collection',
                'description' => 'Test',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('knowledge_collections', ['name' => 'Test Collection']);
    }

    public function test_user_cannot_delete_another_users_collection(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $collection = KnowledgeCollection::create([
            'user_id'     => $user1->id,
            'name'        => 'User1 Collection',
            'description' => '',
        ]);

        $response = $this->actingAs($user2)
            ->deleteJson("/api/knowledge/{$collection->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_upload_a_document_to_a_collection(): void
    {
        Queue::fake();

        Storage::fake('local');

        $user = User::factory()->create();

        $collection = KnowledgeCollection::create([
            'user_id'     => $user->id,
            'name'        => 'My Collection',
            'description' => '',
        ]);

        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $response = $this->actingAs($user)
            ->postJson("/api/knowledge/{$collection->id}/documents", [
                'file' => $file,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('knowledge_documents', [
            'original_name' => 'test.txt',
            'status'        => 'pending',
        ]);
    }

    public function test_rag_service_calculates_cosine_similarity_correctly(): void
    {
        $user = User::factory()->create();

        $collection = KnowledgeCollection::create([
            'user_id'     => $user->id,
            'name'        => 'RAG Collection',
            'description' => '',
        ]);

        $document = KnowledgeDocument::create([
            'collection_id' => $collection->id,
            'filename'      => 'test.txt',
            'original_name' => 'test.txt',
            'mime_type'     => 'text/plain',
            'status'        => 'ready',
        ]);

        KnowledgeSegment::create([
            'document_id' => $document->id,
            'content'     => 'Hello world',
            'embedding'   => json_encode([1.0, 0.0, 0.0]),
            'position'    => 0,
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->once()
            ->andReturn([1.0, 0.0, 0.0]);

        $ragService = new RagService($embeddingService);

        $results = $ragService->search('any query', $collection->id);

        $this->assertNotEmpty($results);
        $this->assertSame(1.0, $results[0]['similarity']);
    }
}
