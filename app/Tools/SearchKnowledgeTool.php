<?php

namespace App\Tools;

use App\Contracts\Tool;
use App\Models\KnowledgeCollection;
use App\Models\User;
use App\Services\RagService;
use Illuminate\Support\Facades\Validator;

class SearchKnowledgeTool implements Tool
{
    public function __construct(
        private RagService $ragService
    ) {}

    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): string
    {
        return 'Pesquisa na base de conhecimento do utilizador por trechos relevantes de documentos, dentro das suas coleções.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Termo ou pergunta a pesquisar na base de conhecimento.',
                ],
                'collection_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'IDs das coleções onde pesquisar. Se omitido, pesquisa em todas as coleções do utilizador.',
                ],
                'top_k' => [
                    'type' => 'integer',
                    'description' => 'Número máximo de resultados a devolver (predefinição 5).',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function authorize(User $user, array $parameters): bool
    {
        return true;
    }

    public function execute(array $parameters, User $user): array
    {
        $validator = Validator::make($parameters, [
            'query'            => ['required', 'string', 'max:1000'],
            'collection_ids'   => ['sometimes', 'array'],
            'collection_ids.*' => ['integer'],
            'top_k'            => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $data = $validator->validated();

        // Nunca confia nos IDs de coleção vindos do modelo — filtra sempre pelas coleções do próprio utilizador
        $ownedCollectionIds = KnowledgeCollection::where('user_id', $user->id)->pluck('id')->toArray();

        if (!empty($data['collection_ids'])) {
            $collectionIds = array_values(array_intersect($data['collection_ids'], $ownedCollectionIds));
        } else {
            $collectionIds = $ownedCollectionIds;
        }

        if (empty($collectionIds)) {
            return ['count' => 0, 'results' => []];
        }

        $topK = $data['top_k'] ?? 5;

        $results = $this->ragService->search($data['query'], $collectionIds, $topK);

        return [
            'count'   => count($results),
            'results' => array_map(fn($r) => [
                'document_name' => $r['document_name'],
                'page'          => $r['page'],
                'similarity'    => round($r['similarity'], 4),
                'excerpt'       => mb_substr($r['content'], 0, 500),
            ], $results),
        ];
    }
}
