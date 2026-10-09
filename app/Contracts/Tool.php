<?php

namespace App\Contracts;

use App\Models\User;

interface Tool
{
    /** Nome único da ferramenta, usado pelo modelo (ex: "calculator"). */
    public function name(): string;

    /** Descrição enviada ao modelo, para ele saber quando usar a ferramenta. */
    public function description(): string;

    /** JSON Schema dos parâmetros aceites (formato OpenAI/OpenRouter function calling). */
    public function parameters(): array;

    /** true se a ferramenta altera dados e exige confirmação antes de executar. */
    public function requiresConfirmation(): bool;

    /** Verifica se o utilizador tem permissão para executar com estes parâmetros. */
    public function authorize(User $user, array $parameters): bool;

    /** Executa a ferramenta e devolve o resultado (array serializável). */
    public function execute(array $parameters, User $user): array;
}
