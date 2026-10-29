<?php

namespace App\Services;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Collection;

class MemorySelector
{
    private const STOP_WORDS = [
        'que', 'com', 'uma', 'para', 'por', 'mais', 'como', 'mas', 'foi', 'ele',
        'ela', 'isso', 'essa', 'este', 'esta', 'esse', 'seu', 'sua', 'seus', 'suas',
        'tem', 'são', 'dos', 'das', 'nos', 'nas', 'num', 'numa', 'pelo', 'pela',
        'pelos', 'pelas', 'quando', 'muito', 'lhe', 'lhes', 'nem', 'eles', 'elas',
        'entre', 'era', 'depois', 'sem', 'mesmo', 'aos', 'durante', 'ter', 'ser',
        'sobre', 'isto', 'aqui', 'nao', 'nada', 'algo', 'cada', 'qual', 'quem',
    ];

    public function select(User $user, string $message, int $limit = 5): Collection
    {
        $memories = Memory::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        if ($memories->isEmpty()) {
            return collect();
        }

        $messageWords = $this->tokenize($message);

        if (empty($messageWords)) {
            return $this->fallback($memories, 2);
        }

        $scored = $memories->map(function (Memory $memory) use ($messageWords) {
            $haystack = implode(' ', array_filter([
                $memory->title,
                $memory->content,
                $memory->category,
            ]));
            $score = count(array_intersect($messageWords, $this->tokenize($haystack)));
            return ['memory' => $memory, 'score' => $score];
        });

        $relevant = $scored
            ->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('memory')
            ->values();

        if ($relevant->isEmpty()) {
            return $this->fallback($memories, 2);
        }

        return $relevant;
    }

    private function fallback(Collection $memories, int $limit): Collection
    {
        return $memories
            ->sortBy(fn (Memory $m) => $m->last_used_at
                ? -$m->last_used_at->timestamp
                : PHP_INT_MAX)
            ->take($limit)
            ->values();
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = $this->removeAccents($text);
        $text = (string) preg_replace('/[^a-z\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, fn (string $w) => mb_strlen($w) > 3);
        $words = array_diff($words, self::STOP_WORDS);
        return array_values(array_unique($words));
    }

    private function removeAccents(string $text): string
    {
        $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
        $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
        return str_replace($from, $to, $text);
    }
}
