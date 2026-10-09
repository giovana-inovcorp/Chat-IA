<?php

namespace Tests\Feature;

use App\Models\KnowledgeCollection;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSegment;
use App\Models\User;
use App\Services\RagService;
use App\Tools\SearchKnowledgeTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchKnowledgeToolTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEmbeddings(): void
    {
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [1, 0, 0]]),
        ]);
    }

    private function makeSegment(int $collectionId, string $content, array $embedding = [1, 0, 0]): KnowledgeSegment
    {
        $document = KnowledgeDocument::create([
            'collection_id' => $collectionId,
            'filename'      => 'doc.txt',
            'original_name' => 'doc.txt',
            'mime_type'     => 'text/plain',
            'status'        => 'ready',
        ]);

        return KnowledgeSegment::create([
            'document_id' => $document->id,
            'content'     => $content,
            'embedding'   => json_encode($embedding),
            'position'    => 0,
        ]);
    }

    public function test_returns_results_from_users_own_collection(): void
    {
        $this->fakeEmbeddings();
        $user = User::factory()->create();
        $collection = KnowledgeCollection::create(['user_id' => $user->id, 'name' => 'Políticas']);
        $this->makeSegment($collection->id, 'Conteúdo sobre férias.');

        $tool = new SearchKnowledgeTool(app(RagService::class));
        $result = $tool->execute(['query' => 'férias', 'collection_ids' => [$collection->id]], $user);

        $this->assertSame(1, $result['count']);
        $this->assertSame('doc.txt', $result['results'][0]['document_name']);
    }

    public function test_ignores_collection_ids_belonging_to_other_user(): void
    {
        $this->fakeEmbeddings();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $collection = KnowledgeCollection::create(['user_id' => $owner->id, 'name' => 'Confidencial']);
        $this->makeSegment($collection->id, 'Informação privada do dono.');

        $tool = new SearchKnowledgeTool(app(RagService::class));
        $result = $tool->execute(['query' => 'informação', 'collection_ids' => [$collection->id]], $intruder);

        $this->assertSame(0, $result['count']);
    }

    public function test_defaults_to_all_own_collections_when_none_given(): void
    {
        $this->fakeEmbeddings();
        $user = User::factory()->create();
        $collection = KnowledgeCollection::create(['user_id' => $user->id, 'name' => 'Geral']);
        $this->makeSegment($collection->id, 'Conteúdo geral.');

        $tool = new SearchKnowledgeTool(app(RagService::class));
        $result = $tool->execute(['query' => 'conteúdo'], $user);

        $this->assertSame(1, $result['count']);
    }

    public function test_requires_query(): void
    {
        $user = User::factory()->create();
        $tool = new SearchKnowledgeTool(app(RagService::class));

        $this->expectException(\InvalidArgumentException::class);
        $tool->execute([], $user);
    }

    public function test_any_authenticated_user_is_authorized(): void
    {
        $user = User::factory()->create();
        $tool = new SearchKnowledgeTool(app(RagService::class));

        $this->assertTrue($tool->authorize($user, []));
    }
}
