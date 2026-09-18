<?php

namespace App\Services;

use App\Models\KnowledgeSegment;

class RagService
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    public function search(string $query, int $collectionId, int $topK = 5): array
    {
        $queryEmbedding = $this->embeddingService->embed($query);

        $segments = KnowledgeSegment::whereHas('document', fn($q) =>
            $q->where('collection_id', $collectionId)->where('status', 'ready')
        )->get(['id', 'content', 'embedding', 'document_id']);

        $results = [];

        foreach ($segments as $segment) {
            if (!$segment->embedding) continue;

            $segmentEmbedding = json_decode($segment->embedding, true);
            if (!is_array($segmentEmbedding)) continue;

            $similarity = $this->cosineSimilarity($queryEmbedding, $segmentEmbedding);

            $results[] = [
                'id'            => $segment->id,
                'content'       => $segment->content,
                'document_id'   => $segment->document_id,
                'similarity'    => $similarity,
                'document_name' => $segment->document->original_name,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $topK);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        $magnitude = sqrt($magA) * sqrt($magB);

        return $magnitude > 0.0 ? $dot / $magnitude : 0.0;
    }
}
