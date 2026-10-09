<?php

namespace App\Services;

use App\Contracts\Tool;
use Illuminate\Support\Collection;

class ToolRegistry
{
    /** @var Collection<int, Tool> */
    protected Collection $tools;

    public function __construct(array $tools = [])
    {
        $this->tools = collect($tools);
    }

    public function all(): Collection
    {
        return $this->tools;
    }

    public function find(string $name): ?Tool
    {
        return $this->tools->first(fn (Tool $tool) => $tool->name() === $name);
    }

    /** Formato esperado pelo parâmetro "tools" da API do OpenRouter. */
    public function schemas(): array
    {
        return $this->tools->map(fn (Tool $tool) => [
            'type' => 'function',
            'function' => [
                'name'        => $tool->name(),
                'description' => $tool->description(),
                'parameters'  => $tool->parameters(),
            ],
        ])->values()->all();
    }
}
