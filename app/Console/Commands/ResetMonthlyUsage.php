<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetMonthlyUsage extends Command
{
    protected $signature = 'usage:reset-monthly';
    protected $description = 'Reset monthly usage counters for all users';

    public function handle()
    {
        \App\Models\User::query()->update([
            'tokens_used_this_month' => 0,
            'cost_used_this_month' => 0,
            'usage_reset_at' => now(),
        ]);

        \Log::info('Monthly usage counters reset for all users.');

        return Command::SUCCESS;
    }
}
