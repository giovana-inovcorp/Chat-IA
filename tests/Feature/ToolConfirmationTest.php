<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ToolExecution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingExecution(User $owner): ToolExecution
    {
        $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Teste']);
        $message = $conversation->messages()->create([
            'role'    => 'assistant',
            'content' => '',
            'model'   => 'openai/gpt-4o-mini',
        ]);

        return ToolExecution::create([
            'user_id'         => $owner->id,
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'tool'            => 'create_note',
            'parameters'      => ['title' => 'Nota', 'content' => 'Conteúdo'],
            'status'          => 'a_aguardar_confirmacao',
            'context'         => [
                'history'      => [],
                'model'        => 'openai/gpt-4o-mini',
                'temperature'  => 0.7,
                'max_tokens'   => 1024,
                'tool_call_id' => 'call_teste',
            ],
        ]);
    }

    public function test_other_user_cannot_confirm_someone_elses_pending_action(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $execution = $this->pendingExecution($owner);

        $response = $this->actingAs($intruder)->post("/api/tool-executions/{$execution->id}/confirm");

        $response->assertStatus(403);
        $this->assertSame('a_aguardar_confirmacao', $execution->fresh()->status);
    }

    public function test_other_user_cannot_reject_someone_elses_pending_action(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $execution = $this->pendingExecution($owner);

        $response = $this->actingAs($intruder)->post("/api/tool-executions/{$execution->id}/reject");

        $response->assertStatus(403);
        $this->assertSame('a_aguardar_confirmacao', $execution->fresh()->status);
    }

    public function test_cannot_confirm_an_action_that_is_not_pending(): void
    {
        $owner = User::factory()->create();
        $execution = $this->pendingExecution($owner);
        $execution->update(['status' => 'concluido']);

        $response = $this->actingAs($owner)->post("/api/tool-executions/{$execution->id}/confirm");

        $response->assertStatus(422);
    }

    public function test_cannot_reject_an_action_that_is_not_pending(): void
    {
        $owner = User::factory()->create();
        $execution = $this->pendingExecution($owner);
        $execution->update(['status' => 'concluido']);

        $response = $this->actingAs($owner)->post("/api/tool-executions/{$execution->id}/reject");

        $response->assertStatus(422);
    }

    public function test_owner_can_reach_confirm_endpoint_for_a_pending_action(): void
    {
        $owner = User::factory()->create();
        $execution = $this->pendingExecution($owner);

        $response = $this->actingAs($owner)->post("/api/tool-executions/{$execution->id}/confirm");

        $response->assertStatus(200);
    }
}
