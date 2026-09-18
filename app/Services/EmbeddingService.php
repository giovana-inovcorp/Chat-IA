<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    public function __construct(
        private string $ollamaUrl = ''
    ) {
        $this->ollamaUrl = config('services.ollama.url', 'http://localhost:11434');
    }

    public function embed(string $text): array
    {
        $response = Http::post("{$this->ollamaUrl}/api/embeddings", [
            'model'  => 'nomic-embed-text',
            'prompt' => $text,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Ollama embedding request failed: " . $response->body());
        }

        return $response->json('embedding');
    }

    public function embedBatch(array $texts): array
    {
        $embeddings = [];

        foreach ($texts as $text) {
            $embeddings[] = $this->embed($text);
            usleep(100_000);
        }

        return $embeddings;
    }
}
