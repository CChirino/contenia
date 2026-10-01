<?php

namespace App\Providers;

use App\Services\Llm\AnthropicClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OpenRouterClient;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, function () {
            return match (config('llm.driver')) {
                'anthropic' => new AnthropicClient,
                'openrouter' => new OpenRouterClient,
                'fake' => new FakeLlmClient,
                default => throw new InvalidArgumentException(
                    'Driver LLM desconocido: '.config('llm.driver')
                ),
            };
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
