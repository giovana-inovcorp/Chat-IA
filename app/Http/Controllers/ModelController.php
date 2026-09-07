<?php

namespace App\Http\Controllers;

use App\Services\OpenRouterService;
use Illuminate\Http\JsonResponse;

class ModelController extends Controller
{
    public function index(OpenRouterService $openRouter): JsonResponse
    {
        if (! $openRouter->isConfigured()) {
            return response()->json([
                'error' => 'A chave de API do OpenRouter não está configurada.',
            ], 500);
        }

        $models = $openRouter->listModels();

        return response()->json([
            'models' => $models,
        ]);
    }
}