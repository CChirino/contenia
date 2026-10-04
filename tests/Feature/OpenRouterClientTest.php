<?php

namespace Tests\Feature;

use App\Services\Llm\LlmException;
use App\Services\Llm\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    use RefreshDatabase;

    private array $schema = [
        'type' => 'object',
        'properties' => ['posts' => ['type' => 'array']],
        'required' => ['posts'],
    ];

    public function test_returns_decoded_tool_call_and_logs(): void
    {
        config()->set('llm.openrouter.api_key', 'or-key');
        config()->set('llm.openrouter.model', 'anthropic/claude-sonnet-4.5');

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'anthropic/claude-sonnet-4.5',
                'choices' => [
                    [
                        'message' => [
                            'tool_calls' => [
                                [
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'emit_result',
                                        'arguments' => json_encode(['posts' => [['hook' => 'Hola']]]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 60],
            ]),
        ]);

        $result = (new OpenRouterClient)->complete('system prompt', 'user prompt', $this->schema, 'plan_generation');

        $this->assertSame(['posts' => [['hook' => 'Hola']]], $result);

        $this->assertDatabaseHas('llm_logs', [
            'purpose' => 'plan_generation',
            'model' => 'anthropic/claude-sonnet-4.5',
            'input_tokens' => 200,
            'output_tokens' => 60,
        ]);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer or-key')
                && $request->hasHeader('X-Title', 'Sabere Marketing')
                && $body['model'] === 'anthropic/claude-sonnet-4.5'
                && $body['max_tokens'] === (int) config('llm.max_tokens')
                && $body['messages'][0] === ['role' => 'system', 'content' => 'system prompt']
                && $body['messages'][1] === ['role' => 'user', 'content' => 'user prompt']
                && $body['tools'][0]['function']['name'] === 'emit_result'
                && $body['tools'][0]['function']['parameters'] === $this->schema
                && $body['tool_choice'] === ['type' => 'function', 'function' => ['name' => 'emit_result']];
        });
    }

    public function test_retries_on_429_and_then_succeeds(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence('openrouter.ai/*')
            ->push(['error' => 'rate-limited'], 429)
            ->push([
                'model' => 'test-model',
                'choices' => [
                    [
                        'message' => [
                            'tool_calls' => [
                                [
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'emit_result',
                                        'arguments' => json_encode(['posts' => []]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ]);

        $result = (new OpenRouterClient)->complete('s', 'u', $this->schema, 'test');

        $this->assertSame(['posts' => []], $result);
        Http::assertSentCount(2);
    }

    public function test_extracts_json_from_content_when_no_tool_calls(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'content' => "```json\n".json_encode(['posts' => [['hook' => 'Hola']]])."\n```",
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $result = (new OpenRouterClient)->complete('s', 'u', $this->schema, 'test');

        $this->assertSame(['posts' => [['hook' => 'Hola']]], $result);
    }

    public function test_http_error_message_uses_readable_detail(): void
    {
        config()->set('llm.retries', 1);

        Http::fake([
            'openrouter.ai/*' => Http::response(
                ['error' => ['metadata' => ['raw' => 'saturado'], 'message' => 'Provider returned error']],
                429
            ),
        ]);

        try {
            (new OpenRouterClient)->complete('s', 'u', $this->schema, 'test');
            $this->fail('Expected LlmException');
        } catch (LlmException $e) {
            $this->assertStringContainsString('saturado', $e->getMessage());
            $this->assertStringNotContainsString('"metadata"', $e->getMessage());
        }
    }

    public function test_http_error_throws_llm_exception(): void
    {
        config()->set('llm.retries', 1);

        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(LlmException::class);

        (new OpenRouterClient)->complete('s', 'u', $this->schema, 'test');
    }

    public function test_response_without_tool_calls_throws_llm_exception(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'texto sin herramienta']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $this->expectException(LlmException::class);

        (new OpenRouterClient)->complete('s', 'u', $this->schema, 'test');
    }

    public function test_invalid_json_arguments_throws_llm_exception(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'tool_calls' => [
                                [
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'emit_result',
                                        'arguments' => '{json roto',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $this->expectException(LlmException::class);

        (new OpenRouterClient)->complete('s', 'u', $this->schema, 'test');
    }
}
