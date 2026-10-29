<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistantController extends Controller
{
    public function index(): JsonResponse
    {
        $assistants = auth()->user()
            ->assistants()
            ->with('knowledgeCollections:id,name')
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get();

        return response()->json($assistants);
    }

    public function store(Request $request, \App\Services\ToolRegistry $toolRegistry): JsonResponse
    {
        $knownTools = $toolRegistry->all()->map(fn ($t) => $t->name())->toArray();

        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'description'      => 'nullable|string|max:500',
            'icon'             => 'nullable|string|max:10',
            'instructions'     => 'nullable|string|max:5000',
            'model'            => 'required|string',
            'temperature'      => 'numeric|min:0|max:2',
            'max_tokens'       => 'integer|min:1|max:8192',
            'is_memory_active' => 'boolean',
            'collection_ids'   => 'nullable|array',
            'collection_ids.*' => 'integer|exists:knowledge_collections,id',
            'allowed_tools'    => 'nullable|array',
            'allowed_tools.*'  => ['string', Rule::in($knownTools)],
        ]);

        $assistant = auth()->user()->assistants()->create($validated);
        $assistant->knowledgeCollections()->sync($validated['collection_ids'] ?? []);

        return response()->json(
            $assistant->refresh()->load('knowledgeCollections:id,name'),
            201
        );
    }

    public function update(Request $request, Assistant $assistant, \App\Services\ToolRegistry $toolRegistry): JsonResponse
    {
        abort_if($assistant->user_id !== auth()->id(), 403);

        $knownTools = $toolRegistry->all()->map(fn ($t) => $t->name())->toArray();

        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:100',
            'description'      => 'nullable|string|max:500',
            'icon'             => 'nullable|string|max:10',
            'instructions'     => 'nullable|string|max:5000',
            'model'            => 'sometimes|required|string',
            'temperature'      => 'numeric|min:0|max:2',
            'max_tokens'       => 'integer|min:1|max:8192',
            'is_memory_active' => 'boolean',
            'collection_ids'   => 'nullable|array',
            'collection_ids.*' => 'integer|exists:knowledge_collections,id',
            'allowed_tools'    => 'nullable|array',
            'allowed_tools.*'  => ['string', Rule::in($knownTools)],
        ]);

        $assistant->update($validated);
        $assistant->knowledgeCollections()->sync($validated['collection_ids'] ?? []);

        return response()->json($assistant->load('knowledgeCollections:id,name'));
    }

    public function duplicate(Assistant $assistant): JsonResponse
    {
        abort_if($assistant->user_id !== auth()->id(), 403);

        $copy = $assistant->replicate();
        $copy->name = "{$assistant->name} (cópia)";
        $copy->save();

        $copy->knowledgeCollections()->sync(
            $assistant->knowledgeCollections()->pluck('id')
        );

        return response()->json($copy->load('knowledgeCollections:id,name'), 201);
    }

    public function archive(Assistant $assistant): JsonResponse
    {
        abort_if($assistant->user_id !== auth()->id(), 403);

        $assistant->update([
            'status' => $assistant->status === 'active' ? 'archived' : 'active',
        ]);

        return response()->json($assistant);
    }

    public function favorite(Assistant $assistant): JsonResponse
    {
        abort_if($assistant->user_id !== auth()->id(), 403);

        $assistant->update(['is_favorite' => ! $assistant->is_favorite]);

        return response()->json($assistant);
    }

    public function tools(\App\Services\ToolRegistry $toolRegistry): JsonResponse
    {
        $tools = $toolRegistry->all()->map(fn ($t) => [
            'name'        => $t->name(),
            'description' => $t->description(),
        ])->values();

        return response()->json($tools);
    }

    public function destroy(Assistant $assistant): JsonResponse
    {
        abort_if($assistant->user_id !== auth()->id(), 403);

        $assistant->delete();

        return response()->json(['message' => 'Assistant deleted successfully.']);
    }
}
