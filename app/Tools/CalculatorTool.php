<?php

namespace App\Tools;

use App\Contracts\Tool;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CalculatorTool implements Tool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Executa operações matemáticas básicas (soma, subtração, multiplicação, divisão, percentagem) de forma determinística. Usa esta ferramenta sempre que a pergunta implicar um cálculo.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'subtract', 'multiply', 'divide', 'percentage'],
                    'description' => 'A operação a realizar. "percentage" calcula "a por cento de b".',
                ],
                'a' => ['type' => 'number', 'description' => 'Primeiro número (ou a percentagem, no caso de "percentage").'],
                'b' => ['type' => 'number', 'description' => 'Segundo número (ou o valor base, no caso de "percentage").'],
            ],
            'required' => ['operation', 'a', 'b'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function authorize(User $user, array $parameters): bool
    {
        return true;
    }

    public function execute(array $parameters, User $user): array
    {
        $validator = Validator::make($parameters, [
            'operation' => ['required', 'string', 'in:add,subtract,multiply,divide,percentage'],
            'a'         => ['required', 'numeric'],
            'b'         => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Parâmetros inválidos: ' . $validator->errors()->first());
        }

        $data = $validator->validated();
        $a = (float) $data['a'];
        $b = (float) $data['b'];

        $result = match ($data['operation']) {
            'add'        => $a + $b,
            'subtract'   => $a - $b,
            'multiply'   => $a * $b,
            'divide'     => $b != 0 ? $a / $b : throw new \InvalidArgumentException('Divisão por zero não é permitida.'),
            'percentage' => ($a / 100) * $b,
        };

        return [
            'operation' => $data['operation'],
            'a'         => $a,
            'b'         => $b,
            'result'    => $result,
        ];
    }
}
