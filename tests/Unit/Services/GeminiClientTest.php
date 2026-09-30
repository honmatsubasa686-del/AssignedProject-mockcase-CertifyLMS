<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GeminiApiException;
use App\Services\AiChat\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @group external-api
 */
class GeminiClientTest extends TestCase
{
    public function test_generate_returns_content_from_gemini_response(): void
    {
        config()->set('ai-chat.gemini.api_key', 'test-api-key');
        config()->set('ai-chat.gemini.model', 'gemini-test');

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'テスト回答です。',
                                ],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 20,
                ],
            ], 200),
        ]);

        $client = app(GeminiClient::class);

        $result = $client->generate(
            [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => 'テスト質問です。',
                        ],
                    ],
                ],
            ],
            'テスト用システムプロンプト'
        );

        $this->assertSame('テスト回答です。', $result['content']);
        $this->assertSame('gemini-test', $result['model']);
        $this->assertSame(10, $result['input_tokens']);
        $this->assertSame(20, $result['output_tokens']);

        Http::assertSent(function (Request $request): bool {
            return $request->url()
                === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-api-key')
                && $request['contents'][0]['parts'][0]['text'] === 'テスト質問です。'
                && $request['systemInstruction']['parts'][0]['text']
                === 'テスト用システムプロンプト';
        });
    }

    public function test_generate_throws_when_gemini_connection_fails(): void
    {
        config()->set('ai-chat.gemini.api_key', 'test-api-key');
        config()->set('ai-chat.gemini.model', 'gemini-test');

        Http::fake([
            '*' => fn () => throw new ConnectionException(
                'Connection failed'
            ),
        ]);

        $this->expectException(GeminiApiException::class);
        $this->expectExceptionMessage('Gemini API connection failed.');

        $client = app(GeminiClient::class);

        $client->generate(
            [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => '通信失敗テストです。',
                        ],
                    ],
                ],
            ],
            'テスト用システムプロンプト'
        );
    }

    public function test_generate_throws_when_gemini_returns_empty_response(): void
    {
        config()->set('ai-chat.gemini.api_key', 'test-api-key');
        config()->set('ai-chat.gemini.model', 'gemini-test');

        Http::fake([
            '*' => Http::response([
                'candidates' => [],
            ], 200),
        ]);

        $this->expectException(GeminiApiException::class);
        $this->expectExceptionMessage('Gemini API returned an empty response.');

        $client = app(GeminiClient::class);

        $client->generate(
            [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => '空応答テストです。',
                        ],
                    ],
                ],
            ],
            'テスト用システムプロンプト'
        );
    }
}
