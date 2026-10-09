<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tools\UserLookupTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLookupToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_by_name(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['name' => 'Maria Silva']);
        User::factory()->create(['name' => 'João Costa']);

        $result = (new UserLookupTool())->execute(['name' => 'Maria'], $admin);

        $this->assertSame(1, $result['count']);
        $this->assertSame('Maria Silva', $result['users'][0]['name']);
    }

    public function test_admin_can_search_by_email(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['email' => 'ana@example.com']);

        $result = (new UserLookupTool())->execute(['email' => 'ana@'], $admin);

        $this->assertSame(1, $result['count']);
    }

    public function test_filters_by_verified_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['email_verified_at' => now()]);
        User::factory()->unverified()->create();

        $result = (new UserLookupTool())->execute(['status' => 'por_verificar'], $admin);

        $this->assertSame(1, $result['count']);
        $this->assertSame('por_verificar', $result['users'][0]['status']);
    }

    public function test_non_admin_is_not_authorized(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $tool = new UserLookupTool();

        $this->assertFalse($tool->authorize($regularUser, []));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->expectException(\InvalidArgumentException::class);
        (new UserLookupTool())->execute(['status' => 'banido'], $admin);
    }
}
