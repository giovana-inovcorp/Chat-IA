<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\KnowledgeCollection;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSegment;
use App\Models\Conversation;
use App\Services\EmbeddingService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_uploading_an_invalid_file_is_rejected(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();

        $collection = KnowledgeCollection::create([
            'user_id'     => $user->id,
            'name'        => 'My Collection',
            'description' => '',
        ]);

        // Extensão fora da whitelist (txt, md, pdf, docx)
        $file = UploadedFile::fake()->create('malware.exe', 10);

        $response = $this->actingAs($user)
            ->postJson("/api/knowledge/{$collection->id}/documents", [
                'file' => $file,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('knowledge_documents', [
            'original_name' => 'malware.exe',
        ]);
    }

    public function test_deleting_a_document_deletes_its_segments(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $collection = KnowledgeCollection::create([
            'user_id'     => $user->id,
            'name'        => 'My Collection',
            'description' => '',
        ]);

        $document = KnowledgeDocument::create([
            'collection_id' => $collection->id,
            'filename'      => 'knowledge/test.txt',
            'original_name' => 'test.txt',
            'mime_type'     => 'text/plain',
            'status'        => 'ready',
        ]);

        $segment = KnowledgeSegment::create([
            'document_id' => $document->id,
            'content'     => 'Hello world',
            'embedding'   => json_encode([1.0, 0.0, 0.0]),
            'position'    => 0,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/knowledge/documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('knowledge_segments', ['id' => $segment->id]);
    }

    public function test_search_only_returns_results_from_the_authorized_collection(): void
    {
        $user = User::factory()->create();

        $collectionA = KnowledgeCollection::create([
            'user_id' => $user->id, 'name' => 'Collection A', 'description' => '',
        ]);
        $collectionB = KnowledgeCollection::create([
            'user_id' => $user->id, 'name' => 'Collection B', 'description' => '',
        ]);

        $documentA = KnowledgeDocument::create([
            'collection_id' => $collectionA->id, 'filename' => 'a.txt',
            'original_name' => 'a.txt', 'mime_type' => 'text/plain', 'status' => 'ready',
        ]);
        $documentB = KnowledgeDocument::create([
            'collection_id' => $collectionB->id, 'filename' => 'b.txt',
            'original_name' => 'b.txt', 'mime_type' => 'text/plain', 'status' => 'ready',
        ]);

        KnowledgeSegment::create([
            'document_id' => $documentA->id, 'content' => 'Segmento da coleção A',
            'embedding' => json_encode([1.0, 0.0, 0.0]), 'position' => 0,
        ]);
        KnowledgeSegment::create([
            'document_id' => $documentB->id, 'content' => 'Segmento da coleção B',
            'embedding' => json_encode([1.0, 0.0, 0.0]), 'position' => 0,
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')->once()->andReturn([1.0, 0.0, 0.0]);

        $ragService = new RagService($embeddingService);
        $results = $ragService->search('qualquer pergunta', $collectionA->id);

        $this->assertCount(1, $results);
        $this->assertSame('Segmento da coleção A', $results[0]['content']);
    }

    public function test_chat_works_without_a_collection_selected(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Título de teste']]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title'   => 'Nova conversa',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'content' => 'Olá, tudo bem?',
                'model'   => 'openai/gpt-4o-mini',
            ]);

        // Sem collection_id: só confirmamos que o pedido não falha
        // (o bloco RAG do MessageController é ignorado).
        $response->assertStatus(200);
    }

    public function test_chat_uses_context_when_a_collection_is_active(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Título de teste']]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $collection = KnowledgeCollection::create([
            'user_id' => $user->id, 'name' => 'Procedimentos', 'description' => '',
        ]);

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('search')
            ->once()
            ->with('Como peço férias?', [$collection->id], 5)
            ->andReturn([[
                'id'            => 1,
                'content'       => 'Procedimento de marcação de ausências: envia um pedido ao teu gestor.',
                'document_id'   => 1,
                'similarity'    => 0.9,
                'document_name' => 'procedimentos.txt',
            ]]);
        $this->app->instance(RagService::class, $ragService);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title'   => 'Nova conversa',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'content'       => 'Como peço férias?',
                'model'         => 'openai/gpt-4o-mini',
                'collection_ids' => [$collection->id],
            ]);

        $response->assertStatus(200);
    }
}
