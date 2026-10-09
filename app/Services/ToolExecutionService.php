<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolExecution;
use App\Models\User;

class ToolExecutionService
{
    public function run(
        array $tc,
        ToolRegistry $toolRegistry,
        User $user,
        Conversation $conversation,
        Message $assistantMessage,
        array &$history,
        string $model,
        float $temperature,
        int $maxTokens,
        ?callable $onExecuting = null
    ): array {
        $tool = $toolRegistry->find($tc['name']);
        $startedAt = microtime(true);
        $arguments = json_decode($tc['arguments'], true) ?? [];

        $execution = ToolExecution::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'tool' => $tc['name'],
            'parameters' => $arguments,
            'status' => 'a_analisar',
        ]);

        if (! $tool) {
            $execution->update([
                'status' => 'erro',
                'error_message' => "Ferramenta desconhecida: {$tc['name']}",
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
            $history[] = ['role' => 'tool', 'tool_call_id' => $tc['id'], 'content' => json_encode(['error' => 'Ferramenta desconhecida.'])];

            return ['status' => 'erro', 'tool' => $tc['name'], 'result' => ['error' => 'Ferramenta desconhecida.'], 'execution' => $execution];
        }

        if (! $tool->authorize($user, $arguments)) {
            $execution->update([
                'status' => 'erro',
                'error_message' => 'Não autorizado.',
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
            $history[] = ['role' => 'tool', 'tool_call_id' => $tc['id'], 'content' => json_encode(['error' => 'Não tens permissão para usar esta ferramenta.'])];

            return ['status' => 'erro', 'tool' => $tc['name'], 'result' => ['error' => 'Não tens permissão para usar esta ferramenta.'], 'execution' => $execution];
        }

        if ($tool->requiresConfirmation()) {
            $execution->update([
                'status'  => 'a_aguardar_confirmacao',
                'context' => [
                    'history'      => $history,
                    'model'        => $model,
                    'temperature'  => $temperature,
                    'max_tokens'   => $maxTokens,
                    'tool_call_id' => $tc['id'],
                ],
            ]);

            return [
                'status' => 'a_aguardar_confirmacao',
                'tool' => $tc['name'],
                'confirmation' => [
                    'execution_id' => $execution->id,
                    'tool'         => $tc['name'],
                    'parameters'   => $arguments,
                ],
                'execution' => $execution,
            ];
        }

        if ($onExecuting) {
            $onExecuting();
        }

        try {
            $result = $tool->execute($arguments, $user);

            $execution->update([
                'status' => 'concluido',
                'result_summary' => json_encode($result),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            $toolResultContent = json_encode($result);
            $finalStatus = 'concluido';
        } catch (\Throwable $e) {
            $execution->update([
                'status' => 'erro',
                'error_message' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
            $toolResultContent = json_encode(['error' => $e->getMessage()]);
            $finalStatus = 'erro';
        }

        $history[] = [
            'role' => 'tool',
            'tool_call_id' => $tc['id'],
            'content' => $toolResultContent,
        ];

        return [
            'status' => $finalStatus,
            'tool' => $tc['name'],
            'result' => json_decode($toolResultContent, true),
            'execution' => $execution,
        ];
    }
}
