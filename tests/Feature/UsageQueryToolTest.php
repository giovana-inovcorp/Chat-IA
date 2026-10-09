<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Tools\UsageQueryTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageQueryToolTest extends TestCase
{
    use RefreshDatabase;

    private function createAuditLog(User $user, string $model, int $inputTokens, int $outputTokens): AuditLog
    {
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Teste']);
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'x', 'model' => $model]);

        return AuditLog::create([
            'message_id'    => $message->id,
            'user_id'       => $user->id,
            'model'         => $model,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd'      => 0.001,
        ]);
    }

    public function test_user_sees_only_own_usage_by_default(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->createAuditLog($user, 'openai/gpt-4o-mini', 100, 50);
        $this->createAuditLog($other, 'openai/gpt-4o-mini', 999, 999);

        $result = (new UsageQueryTool())->execute([], $user);

        $this->assertSame(150, $result['total_tokens']);
    }

    public function test_non_admin_cannot_query_other_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $tool = new UsageQueryTool();

        $this->assertFalse($tool->authorize($user, ['scope' => 'user', 'user_email' => 'x@x.com']));
    }

    public function test_non_admin_cannot_query_global(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $tool = new UsageQueryTool();

        $this->assertFalse($tool->authorize($user, ['scope' => 'global']));
    }

    public function test_admin_can_query_other_user_usage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['email' => 'alvo@example.com']);

        $this->createAuditLog($target, 'openai/gpt-4o-mini', 200, 100);

        $result = (new UsageQueryTool())->execute(['scope' => 'user', 'user_email' => 'alvo@example.com'], $admin);

        $this->assertSame(300, $result['total_tokens']);
    }

    public function test_admin_can_query_global_usage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->createAuditLog($u1, 'openai/gpt-4o-mini', 100, 0);
        $this->createAuditLog($u2, 'openai/gpt-4o-mini', 50, 0);

        $result = (new UsageQueryTool())->execute(['scope' => 'global'], $admin);

        $this->assertSame(150, $result['total_tokens']);
    }

    public function test_invalid_user_email_throws(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->expectException(\InvalidArgumentException::class);
        (new UsageQueryTool())->execute(['scope' => 'user', 'user_email' => 'naoexiste@example.com'], $admin);
    }

    public function test_most_used_model_is_correct(): void
    {
        $user = User::factory()->create();

        $this->createAuditLog($user, 'openai/gpt-4o-mini', 10, 10);
        $this->createAuditLog($user, 'openai/gpt-4o-mini', 10, 10);
        $this->createAuditLog($user, 'anthropic/claude-3', 10, 10);

        $result = (new UsageQueryTool())->execute([], $user);

        $this->assertSame('openai/gpt-4o-mini', $result['most_used_model']);
    }
}
