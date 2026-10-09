<?php

namespace App\Tools;

use App\Contracts\Tool;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateNoteTool implements Tool
{
    public function name(): string
    {
        return 'create_note';
    }

    public function description(): string
    {
        return 'Cria uma nota pessoal para o utilizador autenticado, com um título e um conteúdo. Esta ação altera dados e exige confirmação do utilizador antes de ser executada.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Título curto da nota.'],
                'content' => ['type' => 'string', 'description' => 'Conteúdo completo da nota.'],
            ],
            'required' => ['title', 'content'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function authorize(User $user, array $parameters): bool
    {
        return true;
    }

    public function execute(array $parameters, User $user): array
    {
        $validator = Validator::make($parameters, [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Parâmetros inválidos: ' . $validator->errors()->first());
        }

        $data = $validator->validated();

        $note = Note::create([
            'user_id' => $user->id,
            'title'   => $data['title'],
            'content' => $data['content'],
        ]);

        return [
            'id'      => $note->id,
            'title'   => $note->title,
            'content' => $note->content,
        ];
    }
}
