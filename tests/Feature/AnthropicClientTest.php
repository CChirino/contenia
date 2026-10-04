<?php

namespace Tests\Feature;

use App\Models\LlmLog;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\LlmException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicClientTest extends TestCase
{
    use RefreshDatabase;

    private array $schema = [
        'type' => 'object',
        'properties' => ['posts' => ['type' => 'array']],
        'required' => ['posts'],
    ];

    public function test_returns_tool_use_input_and_logs_call(): void
    {
        config()->set('llm.anthropic.api_key', 'test-key');
        config()->set('llm.anthropic.model', 'claude-sonnet-4-5');

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'name' => 'emit_result',
                        'input' => ['posts' => [['hook' => 'Hola']]],
                    ],
                ],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 45],
            ]),
        ]);

        $client = new AnthropicClient;
        $result = $client->complete('system prompt', 'user prompt', $this->schema, 'plan_generation');

        $this->assertSame(['posts' => [['hook' => 'Hola']]], $result);

        $this->assertDatabaseHas('llm_logs', [
            'purpose' => 'plan_generation',
            'model' => 'claude-sonnet-4-5',
            'input_tokens' => 120,
            'output_tokens' => 45,
        ]);
        $this->assertSame("system prompt\n\nuser prompt", LlmLog::first()->prompt);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $body['model'] === 'claude-sonnet-4-5'
                && $body['max_tokens'] === (int) config('llm.max_tokens')
                && $body['system'] === 'system prompt'
                && $body['tools'][0]['name'] === 'emit_result'
                && $body['tools'][0]['input_schema'] === $this->schema
                && $body['tool_choice'] === ['type' => 'tool', 'name' => 'emit_result'];
        });
    }

    public function test_retries_on_429_and_then_succeeds(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence('api.anthropic.com/*')
            ->push(['error' => 'rate-limited'], 429)
            ->push([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'name' => 'emit_result',
                        'input' => ['posts' => []],
                    ],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
            ]);

        $result = (new AnthropicClient)->complete('s', 'u', $this->schema, 'test');

        $this->assertSame(['posts' => []], $result);
        Http::assertSentCount(2);
    }

    public function test_http_error_throws_llm_exception(): void
    {
        config()->set('llm.retries', 1);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(LlmException::class);

        (new AnthropicClient)->complete('s', 'u', $this->schema, 'test');
    }

    public function test_response_without_tool_use_throws_llm_exception(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'esto no es JSON estructurado'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        $this->expectException(LlmException::class);

        (new AnthropicClient)->complete('s', 'u', $this->schema, 'test');
    }
}
