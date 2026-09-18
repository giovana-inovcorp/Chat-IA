<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessKnowledgeDocument;
use App\Models\KnowledgeCollection;
use App\Models\KnowledgeDocument;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeController extends Controller
{
    public function index(): JsonResponse
    {
        $collections = KnowledgeCollection::withCount('documents')
            ->where('user_id', auth()->id())
            ->get();

        return response()->json($collections);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $collection = KnowledgeCollection::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return response()->json($collection, 201);
    }

    public function destroy(KnowledgeCollection $collection): JsonResponse
    {
        if ($collection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection deleted']);
    }

    public function uploadDocument(Request $request, KnowledgeCollection $collection): JsonResponse
    {
        if ($collection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:20480|mimes:txt,md,pdf,docx',
        ]);

        $file = $request->file('file');
        $path = Storage::putFile('knowledge', $file);

        $document = KnowledgeDocument::create([
            'collection_id' => $collection->id,
            'filename'      => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'status'        => 'pending',
        ]);

        ProcessKnowledgeDocument::dispatch($document);
        $collection->refreshStatus();

        return response()->json($document, 201);
    }

    public function documents(KnowledgeCollection $collection): JsonResponse
    {
        if ($collection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $collection->load(['documents' => function ($query) {
            $query->withCount('segments');
        }]);

        return response()->json($collection);
    }

    public function deleteDocument(KnowledgeDocument $document): JsonResponse
    {
        $collection = $document->collection;

        if ($collection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Storage::delete($document->filename);
        $document->delete();
        $collection->refreshStatus();

        return response()->json(['message' => 'Document deleted']);
    }

    public function reprocessDocument(KnowledgeDocument $document): JsonResponse
    {
        if ($document->collection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($document->status !== 'error') {
            return response()->json(['message' => 'Só é possível reprocessar documentos com erro.'], 422);
        }

        $document->segments()->delete();
        $document->update(['status' => 'pending', 'error_message' => null]);
        $document->collection->refreshStatus();

        ProcessKnowledgeDocument::dispatch($document);

        return response()->json($document);
    }

    public function search(Request $request, KnowledgeCollection $collection, RagService $ragService): JsonResponse
    {
        if ($collection->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $results = $ragService->search($validated['query'], $collection->id);

        return response()->json(['results' => $results]);
    }
}
