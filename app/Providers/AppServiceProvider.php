<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ToolRegistry::class, function () {
            return new \App\Services\ToolRegistry([
                new \App\Tools\CalculatorTool(),
                new \App\Tools\UserLookupTool(),
                new \App\Tools\UsageQueryTool(),
                new \App\Tools\CreateNoteTool(),
                new \App\Tools\SearchKnowledgeTool($this->app->make(\App\Services\RagService::class)),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
