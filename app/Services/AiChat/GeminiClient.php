<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Exceptions\GeminiApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    public function ensureConfigured(): void
    {
        $apiKey = (string) config('ai-chat.gemini.api_key');

        if ($apiKey === '') {
            throw new GeminiApiException('Gemini API key is not configured.');
        }
    }

    public function generate(
        array $contents,
        string $systemPrompt
    ): array {
        $this->ensureConfigured();

        $apiKey = (string) config('ai-chat.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model');

        $payload = [
            'contents' => $contents,
        ];

        if ($systemPrompt !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    [
                        'text' => $systemPrompt,
                    ],
                ],
            ];
        }

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    $payload
                );
        } catch (ConnectionException $e) {
            Log::channel('ai-chat')->warning('Gemini API connection failed.', [
                'model' => $model,
                'message' => $e->getMessage(),
            ]);

            throw new GeminiApiException(
                'Gemini API connection failed.'
            );
        }

        $responseTimeMs = (int) round(
            (microtime(true) - $startedAt) * 1000
        );

        if ($response->failed()) {
            Log::channel('ai-chat')->warning('Gemini API request failed.', [
                'model' => $model,
                'upstream_status' => $response->status(),
                'response_time_ms' => $responseTimeMs,
            ]);

            throw new GeminiApiException(
                'Gemini API request failed.',
                $response->status(),
            );
        }

        $data = $response->json();

        $content = (string) data_get(
            $data,
            'candidates.0.content.parts.0.text',
            ''
        );

        if ($content === '') {
            Log::channel('ai-chat')->warning('Gemini API returned an empty response.', [
                'model' => $model,
                'upstream_status' => $response->status(),
                'response_time_ms' => $responseTimeMs,
            ]);

            throw new GeminiApiException(
                'Gemini API returned an empty response.',
                $response->status(),
            );
        }

        return [
            'content' => $content,
            'model' => $model,
            'input_tokens' => data_get(
                $data,
                'usageMetadata.promptTokenCount'
            ),
            'output_tokens' => data_get(
                $data,
                'usageMetadata.candidatesTokenCount'
            ),
            'response_time_ms' => $responseTimeMs,
        ];
    }
}
