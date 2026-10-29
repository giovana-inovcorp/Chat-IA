<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    private const MAX_ACTIVE = 20;

    public function index(): JsonResponse
    {
        $memories = auth()->user()
            ->memories()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($memories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'    => 'required|string|max:100',
            'content'  => 'required|string|max:500',
            'category' => 'nullable|string|max:50',
            'source'   => ['nullable', 'in:manual,suggested'],
        ]);

        $activeCount = auth()->user()
            ->memories()
            ->where('status', 'active')
            ->count();

        if ($activeCount >= self::MAX_ACTIVE) {
            return response()->json([
                'message' => 'Limite de ' . self::MAX_ACTIVE . ' memórias ativas atingido. Desativa ou apaga uma antes de criar outra.',
            ], 422);
        }

        $memory = auth()->user()->memories()->create([
            ...$validated,
            'source' => $validated['source'] ?? 'manual',
        ]);

        return response()->json($memory, 201);
    }

    public function update(Request $request, Memory $memory): JsonResponse
    {
        abort_if($memory->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'title'    => 'required|string|max:100',
            'content'  => 'required|string|max:500',
            'category' => 'nullable|string|max:50',
        ]);

        $memory->update($validated);

        return response()->json($memory);
    }

    public function toggle(Memory $memory): JsonResponse
    {
        abort_if($memory->user_id !== auth()->id(), 403);

        if ($memory->status === 'inactive') {
            $activeCount = auth()->user()
                ->memories()
                ->where('status', 'active')
                ->count();

            if ($activeCount >= self::MAX_ACTIVE) {
                return response()->json([
                    'message' => 'Limite de ' . self::MAX_ACTIVE . ' memórias ativas atingido. Desativa outra antes de ativar esta.',
                ], 422);
            }
        }

        $memory->update(['status' => $memory->status === 'active' ? 'inactive' : 'active']);

        return response()->json($memory);
    }

    public function destroy(Memory $memory): JsonResponse
    {
        abort_if($memory->user_id !== auth()->id(), 403);

        $memory->delete();

        return response()->json(['message' => 'Memória apagada.']);
    }

    public function suggest(Request $request, \App\Services\OpenRouterService $openRouter): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
        ]);

        $message = \App\Models\Message::find($validated['message_id']);
        if (! $message) abort(404);

        $conversation = \App\Models\Conversation::find($message->conversation_id);
        if (! $conversation || $conversation->user_id !== auth()->id()) abort(403);
        if ($message->role !== 'user') {
            return response()->json(['suggest' => false]);
        }

        $user = auth()->user();
        if (! $user->memory_enabled || ! $openRouter->isConfigured()) {
            return response()->json(['suggest' => false]);
        }

        // Strip injection tags and limit length before embedding in the prompt
        $text = preg_replace('/<\/?mensagem_utilizador>/i', '', $message->content);
        $text = mb_substr($text, 0, 2000);

        $prompt = "Analisa a mensagem do utilizador abaixo e decide se contém informação pessoal reutilizável ou uma preferência estável (ex: nome, língua preferida, área de trabalho, estilo de resposta preferido). Ignora pedidos pontuais, perguntas simples ou conteúdo que não seja sobre o próprio utilizador.\n\n<mensagem_utilizador>\n{$text}\n</mensagem_utilizador>\n\nResponde EXCLUSIVAMENTE com JSON válido, sem texto adicional:\n{\"suggest\": true/false, \"title\": \"máximo 100 caracteres\", \"content\": \"máximo 500 caracteres\"}\n\nSe suggest for false, title e content podem ser strings vazias.";

        try {
            $response = $openRouter->chat(
                [['role' => 'user', 'content' => $prompt]],
                'openai/gpt-4o-mini',
                0.2,
                200
            );

            $raw = trim($response['choices'][0]['message']['content'] ?? '');
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/m', '', $raw);
            $decoded = json_decode($raw, true);

            if (! is_array($decoded) || empty($decoded['suggest'])) {
                return response()->json(['suggest' => false]);
            }

            return response()->json([
                'suggest' => true,
                'title'   => mb_substr($decoded['title'] ?? '', 0, 100),
                'content' => mb_substr($decoded['content'] ?? '', 0, 500),
            ]);
        } catch (\Throwable) {
            return response()->json(['suggest' => false]);
        }
    }

    public function setGlobal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        auth()->user()->update(['memory_enabled' => $validated['enabled']]);

        return response()->json(['memory_enabled' => $validated['enabled']]);
    }
}
