<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation, OpenRouterService $openRouter)
    {
        // Verifica que a conversa pertence ao utilizador autenticado
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        // Valida os dados recebidos
        $validated = $request->validate([
            'content'     => ['required', 'string', 'max:10000'],
            'model'       => ['required', 'string'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens'  => ['sometimes', 'integer', 'min:1', 'max:8192'],
            'file'        => ['sometimes', 'file', 'max:5120', 'mimes:txt,md,pdf,js,ts,php,py,html,css,json,xml,csv'],
            'collection_id' => ['sometimes', 'nullable', 'integer', 'exists:knowledge_collections,id'],
        ]);

        // Verifica se a API key está configurada
        if (! $openRouter->isConfigured()) {
            return response()->json(['error' => 'A chave de API do OpenRouter não está configurada.'], 500);
        }

        // Verifica e reinicia contadores de uso mensais se necessário
        $user = auth()->user();
        $now = now();
        if (is_null($user->usage_reset_at) || $now->month !== \Carbon\Carbon::parse($user->usage_reset_at)->month || $now->year !== \Carbon\Carbon::parse($user->usage_reset_at)->year) {
            $user->tokens_used_this_month = 0;
            $user->cost_used_this_month   = 0;
            $user->usage_reset_at         = $now;
            $user->save();
        }

        // Verifica limite de tokens
        if (! is_null($user->monthly_token_limit) && $user->tokens_used_this_month >= $user->monthly_token_limit) {
            return response()->json(['error' => 'Limite mensal de tokens atingido. Contacta o administrador.', 'limit_reached' => true], 429);
        }

        // Verifica limite de custo
        if (! is_null($user->monthly_cost_limit_usd) && $user->cost_used_this_month >= $user->monthly_cost_limit_usd) {
            return response()->json(['error' => 'Limite mensal de custo atingido. Contacta o administrador.', 'limit_reached' => true], 429);
        }

        // Extrai conteúdo do ficheiro, se enviado
        $messageContent = $validated['content'];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            try {
                $extension = $file->getClientOriginalExtension();
                if ($extension === 'pdf') {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getRealPath());
                    $fileContent = $pdf->getText();
                } else {
                    $fileContent = file_get_contents($file->getRealPath());
                }
                $contextPrefix = "Conteúdo do ficheiro: {$file->getClientOriginalName()}\n\n{$fileContent}\n\n---\n\n";
                $messageContent = $contextPrefix . $validated['content'];
                $estimatedTokens = (int)(mb_strlen($fileContent) / 4);
                $contextLimit = 100000;
                if ($estimatedTokens > $contextLimit) {
                    return response()->json([
                        'error' => 'O ficheiro é demasiado grande para o contexto do modelo. Tenta com um ficheiro mais pequeno ou escolhe um modelo com maior janela de contexto.'
                    ], 422);
                }
            } catch (\Throwable $e) {
                // falha silenciosa — envia apenas a mensagem sem o conteúdo do ficheiro
            }
        }

        // Guarda a mensagem do utilizador
        $userMessage = $conversation->messages()->create([
            'role'    => 'user',
            'content' => $messageContent,
            'model'   => null,
        ]);

        // Gera título automático se for a primeira mensagem
        if ($conversation->messages()->count() === 1) {
            $fallback = str($validated['content'])->limit(60)->toString();
            try {
                $titleResponse = $openRouter->chat(
                    [['role' => 'user', 'content' => 'Generate a short title (max 6 words, no quotes, no punctuation at the end) that summarizes this user message: ' . $validated['content']]],
                    'openai/gpt-4o-mini',
                    0.7,
                    32
                );
                $title = trim($titleResponse['choices'][0]['message']['content'] ?? $fallback);
                if ($title === '') $title = $fallback;
            } catch (\Throwable $e) {
                $title = $fallback;
            }
            $conversation->update(['title' => $title]);
        }

        // Busca o histórico completo da conversa para enviar ao modelo
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn($msg) => [
                'role'    => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();

        // RAG: injeta contexto da base de conhecimento se uma coleção foi selecionada
        $ragResults = [];
        if (!empty($validated['collection_id'])) {
            $ragService = app(\App\Services\RagService::class);
            $ragResults = $ragService->search($validated['content'], $validated['collection_id'], 5);
            if (!empty($ragResults)) {
                $context = "Contexto relevante da base de conhecimento:\n\n";
                foreach ($ragResults as $result) {
                    $context .= "--- Fonte: {$result['document_name']} ---\n{$result['content']}\n\n";
                }
                $context .= "---\n\nCom base no contexto acima, responde à seguinte pergunta:\n\n";
                $history[count($history)-1]['content'] = $context . $history[count($history)-1]['content'];
            }
        }

        $model       = $validated['model'];
        $temperature = $validated['temperature'] ?? 0.7;
        $maxTokens   = $validated['max_tokens'] ?? 1024;

        // Cria a mensagem do assistente já no banco (sem conteúdo ainda)
        $assistantMessage = $conversation->messages()->create([
            'role'    => 'assistant',
            'content' => '',
            'model'   => $model,
        ]);

        // Actualiza o updated_at da conversa (sobe para o topo da sidebar)
        $conversation->touch();

        // Devolve uma resposta em streaming (SSE)
        return response()->stream(function () use (
            $openRouter, $history, $model, $temperature, $maxTokens, $assistantMessage, $conversation, $user, $ragResults
        ) {
            $fullContent = '';

            $response = $openRouter->streamChat($history, $model, $temperature, $maxTokens);

            $body = $response->getBody();

            try {
                while (! $body->eof()) {
                    $line = trim($this->readLine($body));

                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);

                        if ($data === '[DONE]') {
                            // Guarda o conteúdo completo no banco
                            $assistantMessage->update(['content' => $fullContent]);

                            // Estima tokens (aproximação: 1 token ≈ 4 chars)
                            $inputTokens = (int)(mb_strlen(collect($history)->pluck('content')->join(' ')) / 4);
                            $outputTokens = (int)(mb_strlen($fullContent) / 4);

                            // Custo estimado (valores médios por modelo)
                            $costPer1kInput = 0.00015;
                            $costPer1kOutput = 0.0006;
                            $costUsd = (($inputTokens / 1000) * $costPer1kInput) + (($outputTokens / 1000) * $costPer1kOutput);

                            AuditLog::create([
                                'message_id'    => $assistantMessage->id,
                                'user_id'       => $conversation->user_id,
                                'model'         => $model,
                                'input_tokens'  => $inputTokens,
                                'output_tokens' => $outputTokens,
                                'cost_usd'      => round($costUsd, 8),
                            ]);

                            $user->increment('tokens_used_this_month', $inputTokens + $outputTokens);
                            $user->increment('cost_used_this_month', round($costUsd, 6));

                            $usageData = [
                                'tokens_used' => $user->fresh()->tokens_used_this_month,
                                'token_limit' => $user->monthly_token_limit,
                                'cost_used'   => $user->fresh()->cost_used_this_month,
                                'cost_limit'  => $user->monthly_cost_limit_usd,
                            ];
                            echo "data: " . json_encode(['usage' => $usageData]) . "\n\n";
                            ob_flush();
                            flush();

                            echo "data: [DONE]\n\n";
                            ob_flush();
                            flush();

                            if (!empty($ragResults)) {
                                echo "data: " . json_encode(['references' => $ragResults]) . "\n\n";
                                ob_flush();
                                flush();
                            }

                            break;
                        }

                        $decoded = json_decode($data, true);
                        $chunk   = $decoded['choices'][0]['delta']['content'] ?? '';

                        if ($chunk !== '') {
                            $fullContent .= $chunk;

                            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Stream error: ' . $e->getMessage() . ' at line ' . $e->getLine());
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Lê uma linha do stream de resposta.
     */
    private function readLine($stream): string
    {
        $line = '';
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return $line;
    }
}