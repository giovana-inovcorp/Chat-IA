<?php

namespace Tests\Feature;

use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssistant(User $user, array $attrs = []): Assistant
    {
        return Assistant::create(array_merge([
            'user_id'     => $user->id,
            'name'        => 'Bot',
            'model'       => 'openai/gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens'  => 512,
            'status'      => 'active',
        ], $attrs));
    }

    private function makeConversation(User $user, array $attrs = []): Conversation
    {
        return Conversation::create(array_merge([
            'user_id' => $user->id,
            'title'   => 'Test',
        ], $attrs));
    }

    public function test_switching_assistant_updates_id_and_appends_history(): void
    {
        $user = User::factory()->create();

        $a1 = $this->makeAssistant($user, ['name' => 'Bot One', 'instructions' => 'inst-one']);
        $a2 = $this->makeAssistant($user, ['name' => 'Bot Two', 'instructions' => 'inst-two']);

        $conversation = $this->makeConversation($user, [
            'assistant_id'       => $a1->id,
            'assistant_snapshot' => [
                'name' => $a1->name, 'instructions' => 'inst-one',
                'model' => 'openai/gpt-4o-mini', 'temperature' => 0.7,
                'max_tokens' => 512, 'collection_ids' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/conversations/{$conversation->id}/assistant", ['assistant_id' => $a2->id]);

        $response->assertOk();
        $response->assertJsonPath('assistant.id', $a2->id);

        $this->assertArrayNotHasKey('assistant_snapshot', $response->json());
        $this->assertArrayNotHasKey('assistant_history', $response->json());

        $conversation->refresh();
        $this->assertEquals($a2->id, $conversation->assistant_id);

        $history = $conversation->assistant_history;
        $this->assertCount(1, $history);
        $this->assertEquals('inst-one', $history[0]['previous_snapshot']['instructions']);
        $this->assertArrayHasKey('changed_at', $history[0]);
    }

    public function test_switching_to_null_clears_assistant_and_appends_history(): void
    {
        $user = User::factory()->create();

        $a1 = $this->makeAssistant($user, ['name' => 'Bot One', 'instructions' => 'inst-one']);

        $conversation = $this->makeConversation($user, [
            'assistant_id'       => $a1->id,
            'assistant_snapshot' => [
                'name' => $a1->name, 'instructions' => 'inst-one',
                'model' => 'openai/gpt-4o-mini', 'temperature' => 0.7,
                'max_tokens' => 512, 'collection_ids' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/conversations/{$conversation->id}/assistant", ['assistant_id' => null]);

        $response->assertOk();
        $this->assertNull($response->json('assistant'));

        $conversation->refresh();
        $this->assertNull($conversation->assistant_id);

        $history = $conversation->assistant_history;
        $this->assertCount(1, $history);
        $this->assertEquals('inst-one', $history[0]['previous_snapshot']['instructions']);
    }

    public function test_switching_to_another_users_assistant_returns_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $conversation = $this->makeConversation($owner);
        $foreignAssistant = $this->makeAssistant($other);

        $this->actingAs($owner)
            ->patchJson("/api/conversations/{$conversation->id}/assistant", ['assistant_id' => $foreignAssistant->id])
            ->assertForbidden();
    }

    public function test_another_user_cannot_switch_assistant_on_foreign_conversation(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();

        $conversation = $this->makeConversation($owner);
        $assistant    = $this->makeAssistant($attacker);

        $this->actingAs($attacker)
            ->patchJson("/api/conversations/{$conversation->id}/assistant", ['assistant_id' => $assistant->id])
            ->assertForbidden();
    }
}
