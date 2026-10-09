<?php

namespace App\Tools;

use App\Contracts\Tool;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UserLookupTool implements Tool
{
    public function name(): string
    {
        return 'search_users';
    }

    public function description(): string
    {
        return 'Procura utilizadores existentes na aplicação por nome, email ou estado (verificado/por verificar). Apenas administradores podem usar esta ferramenta. Devolve só os campos essenciais.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Filtra por nome (pesquisa parcial).'],
                'email' => ['type' => 'string', 'description' => 'Filtra por email (pesquisa parcial).'],
                'status' => ['type' => 'string', 'enum' => ['verificado', 'por_verificar'], 'description' => 'Filtra pelo estado de verificação da conta.'],
            ],
            'required' => [],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function authorize(User $user, array $parameters): bool
    {
        return (bool) $user->is_admin;
    }

    public function execute(array $parameters, User $user): array
    {
        $validator = Validator::make($parameters, [
            'name'   => ['sometimes', 'string', 'max:255'],
            'email'  => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:verificado,por_verificar'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Parâmetros inválidos: ' . $validator->errors()->first());
        }

        $data = $validator->validated();

        $query = User::query();

        if (!empty($data['name'])) {
            $query->where('name', 'like', '%' . $data['name'] . '%');
        }

        if (!empty($data['email'])) {
            $query->where('email', 'like', '%' . $data['email'] . '%');
        }

        if (!empty($data['status'])) {
            if ($data['status'] === 'verificado') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        $users = $query->limit(20)->get(['id', 'name', 'email', 'email_verified_at']);

        return [
            'count' => $users->count(),
            'users' => $users->map(fn ($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'status' => $u->email_verified_at ? 'verificado' : 'por_verificar',
            ])->all(),
        ];
    }
}
