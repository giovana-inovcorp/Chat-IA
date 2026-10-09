<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Tools\CreateNoteTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNoteToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_note_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $result = (new CreateNoteTool())->execute([
            'title'   => 'Lembrete',
            'content' => 'Enviar relatório até sexta-feira.',
        ], $user);

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'title'   => 'Lembrete',
            'content' => 'Enviar relatório até sexta-feira.',
        ]);
        $this->assertSame('Lembrete', $result['title']);
    }

    public function test_requires_title(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        (new CreateNoteTool())->execute(['content' => 'Sem título'], $user);
    }

    public function test_requires_content(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        (new CreateNoteTool())->execute(['title' => 'Sem conteúdo'], $user);
    }

    public function test_title_cannot_exceed_max_length(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        (new CreateNoteTool())->execute([
            'title'   => str_repeat('a', 256),
            'content' => 'Conteúdo válido.',
        ], $user);
    }

    public function test_any_authenticated_user_is_authorized(): void
    {
        $user = User::factory()->create();

        $this->assertTrue((new CreateNoteTool())->authorize($user, []));
    }

    public function test_requires_confirmation(): void
    {
        $this->assertTrue((new CreateNoteTool())->requiresConfirmation());
    }

    public function test_does_not_persist_a_note_when_validation_fails(): void
    {
        $user = User::factory()->create();

        try {
            (new CreateNoteTool())->execute(['title' => '', 'content' => ''], $user);
        } catch (\InvalidArgumentException $e) {
            // esperado
        }

        $this->assertSame(0, Note::count());
    }
}
