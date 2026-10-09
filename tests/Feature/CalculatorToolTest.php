<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tools\CalculatorTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculatorToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_addition(): void
    {
        $user = User::factory()->create();
        $result = (new CalculatorTool())->execute(['operation' => 'add', 'a' => 2, 'b' => 3], $user);
        $this->assertSame(5.0, $result['result']);
    }

    public function test_subtraction(): void
    {
        $user = User::factory()->create();
        $result = (new CalculatorTool())->execute(['operation' => 'subtract', 'a' => 10, 'b' => 4], $user);
        $this->assertSame(6.0, $result['result']);
    }

    public function test_multiplication(): void
    {
        $user = User::factory()->create();
        $result = (new CalculatorTool())->execute(['operation' => 'multiply', 'a' => 6, 'b' => 7], $user);
        $this->assertSame(42.0, $result['result']);
    }

    public function test_division(): void
    {
        $user = User::factory()->create();
        $result = (new CalculatorTool())->execute(['operation' => 'divide', 'a' => 20, 'b' => 4], $user);
        $this->assertSame(5.0, $result['result']);
    }

    public function test_division_by_zero_throws(): void
    {
        $user = User::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        (new CalculatorTool())->execute(['operation' => 'divide', 'a' => 10, 'b' => 0], $user);
    }

    public function test_percentage(): void
    {
        $user = User::factory()->create();
        $result = (new CalculatorTool())->execute(['operation' => 'percentage', 'a' => 15, 'b' => 200], $user);
        $this->assertSame(30.0, $result['result']);
    }

    public function test_invalid_operation_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        (new CalculatorTool())->execute(['operation' => 'power', 'a' => 2, 'b' => 3], $user);
    }
}
