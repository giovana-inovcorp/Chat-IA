<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->apiUrl = config('services.openrouter.api_url');
    }

    /**
     * Verifica se a chave de API está configurada.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Busca a lista de modelos disponíveis no OpenRouter.
     */
    public function listModels(): array
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->apiUrl}/models");

        if ($response->failed()) {
            Log::error('OpenRouter: falha ao buscar modelos', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        return $response->json('data', []);
    }

    /**
     * Envia uma conversa para o modelo e recebe a resposta em streaming.
     * Retorna a resposta HTTP "crua" para ser processada como stream no controller.
     */
    public function streamChat(array $messages, string $model, float $temperature = 0.7, int $maxTokens = 1024)
    {
        return Http::withToken($this->apiKey)
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post("{$this->apiUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stream' => true,
            ]);
    }

    /**
     * Envia mensagens e retorna a resposta completa (sem streaming).
     */
    public function chat(array $messages, string $model, float $temperature = 0.7, int $maxTokens = 256): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->apiUrl}/chat/completions", [
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'stream'      => false,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenRouter chat() failed: ' . $response->body());
        }

        return $response->json();
    }
}