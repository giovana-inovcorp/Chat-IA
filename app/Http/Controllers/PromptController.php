<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function index(): JsonResponse
    {
        $prompts = auth()->user()->prompts()->orderBy('name')->get();

        return response()->json($prompts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $prompt = auth()->user()->prompts()->create($validated);

        return response()->json($prompt, 201);
    }

    public function update(Request $request, Prompt $prompt): JsonResponse
    {
        if ($prompt->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $prompt->update($validated);

        return response()->json($prompt);
    }

    public function destroy(Prompt $prompt): JsonResponse
    {
        if ($prompt->user_id !== auth()->id()) {
            abort(403);
        }

        $prompt->delete();

        return response()->json(['message' => 'Prompt apagado.']);
    }
}
