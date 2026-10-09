<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ToolExecution;
use App\Models\User;
use App\Services\ToolExecutionService;
use App\Services\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolExecutionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function conversationWithMessage(User $user): array
    {
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Teste']);
        $message = $conversation->messages()->create([
            'role'    => 'assistant',
            'content' => '',
            'model'   => 'openai/gpt-4o-mini',
        ]);

        return [$conversation, $message];
    }

    public function test_records_a_successful_tool_execution_with_all_fields(): void
    {
        $user = User::factory()->create();
        [$conversation, $message] = $this->conversationWithMessage($user);
        $history = [];

        $tc = [
            'id' => 'call_1',
            'name' => 'calculator',
            'arguments' => json_encode(['operation' => 'add', 'a' => 2, 'b' => 3]),
        ];

        $outcome = (new ToolExecutionService())->run(
            $tc,
            app(ToolRegistry::class),
            $user,
            $conversation,
            $message,
            $history,
            'openai/gpt-4o-mini',
            0.7,
            1024
        );

        $this->assertSame('concluido', $outcome['status']);

        $execution = ToolExecution::first();
        $this->assertNotNull($execution);
        $this->assertSame('calculator', $execution->tool);
        $this->assertSame($user->id, $execution->user_id);
        $this->assertSame($conversation->id, $execution->conversation_id);
        $this->assertSame($message->id, $execution->message_id);
        $this->assertSame('concluido', $execution->status);
        $this->assertNotNull($execution->result_summary);
        $this->assertNotNull($execution->duration_ms);

        $this->assertCount(1, $history);
        $this->assertSame('tool', $history[0]['role']);
    }

    public function test_tool_failure_is_recorded_and_conversation_keeps_going(): void
    {
        $user = User::factory()->create();
        [$conversation, $message] = $this->conversationWithMessage($user);
        $history = [];

        $tc = [
            'id' => 'call_2',
            'name' => 'calculator',
            'arguments' => json_encode(['operation' => 'divide', 'a' => 10, 'b' => 0]),
        ];

        $outcome = (new ToolExecutionService())->run(
            $tc,
            app(ToolRegistry::class),
            $user,
            $conversation,
            $message,
            $history,
            'openai/gpt-4o-mini',
            0.7,
            1024
        );

        $this->assertSame('erro', $outcome['status']);

        $execution = ToolExecution::first();
        $this->assertSame('erro', $execution->status);
        $this->assertNotNull($execution->error_message);

        // a conversa continua: o histórico recebe a mensagem de erro da
        // ferramenta em vez de a execução rebentar com uma exceção.
        $this->assertCount(1, $history);
        $this->assertSame('tool', $history[0]['role']);
        $this->assertSame('call_2', $history[0]['tool_call_id']);
    }

    public function test_unknown_tool_is_rejected_and_recorded(): void
    {
        $user = User::factory()->create();
        [$conversation, $message] = $this->conversationWithMessage($user);
        $history = [];

        $tc = [
            'id' => 'call_3',
            'name' => 'ferramenta_inexistente',
            'arguments' => '{}',
        ];

        $outcome = (new ToolExecutionService())->run(
            $tc,
            app(ToolRegistry::class),
            $user,
            $conversation,
            $message,
            $history,
            'openai/gpt-4o-mini',
            0.7,
            1024
        );

        $this->assertSame('erro', $outcome['status']);
        $this->assertSame('erro', ToolExecution::first()->status);
    }
}
