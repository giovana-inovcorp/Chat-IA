<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class ConversationController extends Controller
{
    /**
     * Lista todas as conversas do utilizador autenticado.
     */
    public function index(): JsonResponse
    {
        $conversations = auth()->user()
            ->conversations()
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'is_pinned', 'created_at', 'updated_at']);

        return response()->json($conversations);
    }

    public function pin(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$conversation->is_pinned) {
            $pinnedCount = auth()->user()->conversations()->where('is_pinned', true)->count();
            if ($pinnedCount >= 10) {
                return response()->json(['message' => 'Limite de 10 conversas fixadas atingido.'], 422);
            }
        }

        $conversation->update(['is_pinned' => !$conversation->is_pinned]);

        return response()->json($conversation);
    }

    /**
     * Cria uma nova conversa vazia.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assistant_id' => ['nullable', 'integer', 'exists:assistants,id'],
        ]);

        $assistantId = $validated['assistant_id'] ?? null;
        $snapshot    = null;

        if ($assistantId) {
            $assistant = \App\Models\Assistant::find($assistantId);
            abort_if(! $assistant || $assistant->user_id !== auth()->id() || $assistant->status !== 'active', 403);

            $snapshot = [
                'name'           => $assistant->name,
                'instructions'   => $assistant->instructions,
                'model'          => $assistant->model,
                'temperature'    => $assistant->temperature,
                'max_tokens'     => $assistant->max_tokens,
                'collection_ids' => $assistant->knowledgeCollections()->pluck('id')->toArray(),
            ];
        }

        $conversation = auth()->user()
            ->conversations()
            ->create([
                'title'              => 'Nova conversa',
                'assistant_id'       => $assistantId,
                'assistant_snapshot' => $snapshot,
            ]);

        return response()->json($conversation->load('assistant:id,name,icon'), 201);
    }

    /**
     * Devolve uma conversa com todas as suas mensagens.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        // Garante que o utilizador só acede às suas próprias conversas
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $conversation->load(['messages.memories:id,title', 'assistant:id,name,icon']);

        return response()->json($conversation);
    }

    /**
     * Apaga uma conversa e todas as suas mensagens (cascade).
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $conversation->delete();

        return response()->json(['message' => 'Conversa apagada.']);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $term = $validated['q'];

        $conversations = auth()->user()
            ->conversations()
            ->where(function ($query) use ($term) {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhereHas('messages', function ($q) use ($term) {
                        $q->where('content', 'like', "%{$term}%");
                    });
            })
            ->with(['messages' => function ($q) use ($term) {
                $q->where('content', 'like', "%{$term}%")->orderBy('created_at')->limit(1);
            }])
            ->limit(20)
            ->get(['id', 'title', 'updated_at']);

        $results = $conversations->map(function ($conversation) use ($term) {
            $message = $conversation->messages->first();
            $snippet = null;

            if ($message) {
                $content = $message->content;
                $pos = mb_stripos($content, $term);
                $start = max(0, $pos - 50);
                $raw = mb_substr($content, $start, 120);
                $snippet = ($start > 0 ? '…' : '') . $raw . (mb_strlen($content) > $start + 120 ? '…' : '');
            }

            return [
                'id'         => $conversation->id,
                'title'      => $conversation->title,
                'updated_at' => $conversation->updated_at,
                'snippet'    => $snippet,
            ];
        });

        return response()->json($results);
    }

    public function switchAssistant(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'assistant_id' => ['nullable', 'integer', 'exists:assistants,id'],
        ]);

        $assistantId = $validated['assistant_id'];
        $snapshot    = null;

        if ($assistantId) {
            $assistant = \App\Models\Assistant::find($assistantId);
            abort_if(! $assistant || $assistant->user_id !== auth()->id() || $assistant->status !== 'active', 403);

            $snapshot = [
                'name'           => $assistant->name,
                'instructions'   => $assistant->instructions,
                'model'          => $assistant->model,
                'temperature'    => $assistant->temperature,
                'max_tokens'     => $assistant->max_tokens,
                'collection_ids' => $assistant->knowledgeCollections()->pluck('id')->toArray(),
            ];
        }

        $history = $conversation->assistant_history ?? [];
        if ($conversation->assistant_snapshot) {
            $history[] = [
                'changed_at'        => now()->toIso8601String(),
                'previous_snapshot' => $conversation->assistant_snapshot,
            ];
        }

        $conversation->update([
            'assistant_id'       => $assistantId,
            'assistant_snapshot' => $snapshot,
            'assistant_history'  => $history,
        ]);

        return response()->json($conversation->load('assistant:id,name,icon'));
    }

    public function export(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $conversation->load('messages');

        if ($conversation->messages->isEmpty()) {
            abort(422, 'A conversa não tem mensagens.');
        }

        $format = $request->input('format', 'md');

        $firstModel = $conversation->messages
            ->firstWhere('role', 'assistant')?->model ?? 'N/A';

        $markdown = "# {$conversation->title}\n\n";
        $markdown .= "Data: {$conversation->created_at->format('d/m/Y')}\n";
        $markdown .= "Modelo: {$firstModel}\n\n";
        $markdown .= "---\n\n";

        foreach ($conversation->messages as $message) {
            if ($message->role === 'user') {
                $markdown .= "**Utilizador:**\n{$message->content}\n\n";
            } else {
                $markdown .= "**Assistente:**\n{$message->content}\n\n";
            }
        }

        $slug = Str::slug($conversation->title) . '-' . now()->format('Y-m-d');

        if ($format === 'pdf') {
            $converter = new CommonMarkConverter();
            $html = $converter->convert($markdown)->getContent();

            $fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 13px; line-height: 1.6; color: #1a1a1a; padding: 40px; }
h1 { font-size: 20px; margin-bottom: 4px; }
p { margin: 4px 0 16px; color: #555; font-size: 11px; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
strong { font-weight: bold; }
code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; font-size: 11px; }
pre { background: #f4f4f4; padding: 12px; border-radius: 4px; font-size: 11px; overflow-wrap: break-word; }
</style></head><body>' . $html . '</body></html>';

            $pdf = Pdf::loadHtml($fullHtml);
            return $pdf->download($slug . '.pdf');
        }

        return response()->streamDownload(
            fn () => print($markdown),
            $slug . '.md',
            ['Content-Type' => 'text/markdown']
        );
    }
}