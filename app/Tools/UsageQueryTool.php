<?php

namespace App\Tools;

use App\Contracts\Tool;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UsageQueryTool implements Tool
{
    public function name(): string
    {
        return 'query_usage';
    }

    public function description(): string
    {
        return 'Consulta o consumo de tokens e custo de IA. Por omissão devolve os dados do próprio utilizador. Administradores podem consultar dados globais (scope="global") ou de outro utilizador (scope="user" + user_email). Usa esta ferramenta para perguntas sobre consumo, tokens utilizados, custo, ou qual foi o modelo mais utilizado.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', '7_days', '30_days', 'this_month', 'all_time'],
                    'description' => 'Período a consultar. Por omissão, "this_month".',
                ],
                'scope' => [
                    'type' => 'string',
                    'enum' => ['self', 'user', 'global'],
                    'description' => '"self" (omissão) = dados do próprio utilizador. "user" = outro utilizador (requer user_email, só admin). "global" = todos os utilizadores (só admin).',
                ],
                'user_email' => [
                    'type' => 'string',
                    'description' => 'Email do utilizador a consultar, quando scope="user".',
                ],
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
        $scope = $parameters['scope'] ?? 'self';

        if ($scope === 'self') {
            return true;
        }

        return (bool) $user->is_admin;
    }

    public function execute(array $parameters, User $user): array
    {
        $validator = Validator::make($parameters, [
            'period'     => ['sometimes', 'string', 'in:today,7_days,30_days,this_month,all_time'],
            'scope'      => ['sometimes', 'string', 'in:self,user,global'],
            'user_email' => ['required_if:scope,user', 'sometimes', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Parâmetros inválidos: ' . $validator->errors()->first());
        }

        $data   = $validator->validated();
        $period = $data['period'] ?? 'this_month';
        $scope  = $data['scope'] ?? 'self';

        $query = AuditLog::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', now()->toDateString());
                break;
            case '7_days':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '30_days':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
            case 'this_month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'all_time':
                break;
        }

        $targetLabel = 'os teus dados';

        if ($scope === 'self') {
            $query->where('user_id', $user->id);
        } elseif ($scope === 'user') {
            $targetUser = User::where('email', $data['user_email'])->first();

            if (! $targetUser) {
                throw new \InvalidArgumentException('Não foi encontrado nenhum utilizador com esse email.');
            }

            $query->where('user_id', $targetUser->id);
            $targetLabel = "os dados de {$targetUser->name}";
        } else {
            $targetLabel = 'os dados globais de todos os utilizadores';
        }

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost_usd')
            ->selectRaw('COUNT(*) as messages')
            ->first();

        $topModel = (clone $query)
            ->selectRaw('model, COUNT(*) as uses')
            ->groupBy('model')
            ->orderByDesc('uses')
            ->first();

        return [
            'scope'           => $scope,
            'period'          => $period,
            'target'          => $targetLabel,
            'messages'        => (int) $totals->messages,
            'input_tokens'    => (int) $totals->input_tokens,
            'output_tokens'   => (int) $totals->output_tokens,
            'total_tokens'    => (int) $totals->input_tokens + (int) $totals->output_tokens,
            'cost_usd'        => round((float) $totals->cost_usd, 6),
            'most_used_model' => $topModel->model ?? null,
        ];
    }
}
