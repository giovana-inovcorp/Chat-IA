<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Services\MemorySelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemorySelectorTest extends TestCase
{
    use RefreshDatabase;

    private function makeMemory(User $user, array $attrs = []): Memory
    {
        return Memory::create(array_merge([
            'user_id'  => $user->id,
            'title'    => 'título',
            'content'  => 'conteúdo',
            'status'   => 'active',
            'source'   => 'manual',
            'category' => null,
        ], $attrs));
    }

    public function test_relevant_memory_is_selected_over_irrelevant(): void
    {
        $user = User::factory()->create();

        $relevant = $this->makeMemory($user, [
            'title'   => 'Python programming',
            'content' => 'utilizador sabe programar Python',
        ]);
        $this->makeMemory($user, [
            'title'   => 'Comida favorita',
            'content' => 'gosta pizza italiana',
        ]);

        $result = (new MemorySelector())->select($user, 'ajuda-me a escrever código Python');

        $this->assertCount(1, $result);
        $this->assertEquals($relevant->id, $result->first()->id);
    }

    public function test_fallback_applies_when_nothing_matches(): void
    {
        $user = User::factory()->create();

        $older = $this->makeMemory($user, [
            'title'        => 'coisa aleatória',
            'content'      => 'dado irrelevante',
            'last_used_at' => now()->subDays(10),
        ]);
        $newer = $this->makeMemory($user, [
            'title'        => 'outra coisa aleatória',
            'content'      => 'mais dados irrelevantes',
            'last_used_at' => now()->subDay(),
        ]);

        $result = (new MemorySelector())->select($user, 'programação viagens música');

        $this->assertCount(2, $result);
        $this->assertEquals($newer->id, $result->first()->id);
        $this->assertEquals($older->id, $result->last()->id);
    }

    public function test_inactive_memory_is_never_selected(): void
    {
        $user = User::factory()->create();

        $this->makeMemory($user, [
            'title'   => 'Python programming',
            'content' => 'utilizador sabe programar Python',
            'status'  => 'inactive',
        ]);

        $result = (new MemorySelector())->select($user, 'ajuda-me a escrever código Python');

        $this->assertCount(0, $result);
    }
}
