<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChatDailyLimitException;
use App\Exceptions\GeminiApiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Throwable;

class AiChatService
{
    public function __construct(
        private readonly AiChatPromptBuilder $promptBuilder,
        private readonly AiChatContextBuilder $contextBuilder,
        private readonly GeminiClient $geminiClient,
    ) {}

    public function send(
        AiChatConversation $conversation,
        string $content
    ): array {
        $dailyLimit = (int) config('ai-chat.daily_limit', 50);

        $sentToday = AiChatMessage::query()
            ->where('role', AiChatMessageRole::User->value)
            ->whereDate('created_at', today())
            ->whereHas(
                'conversation',
                fn ($query) => $query->where('user_id', $conversation->user_id)
            )
            ->count();

        if ($sentToday >= $dailyLimit) {
            throw new AiChatDailyLimitException(
                'AI chat daily send limit reached.'
            );
        }

        $userMessage = AiChatMessage::create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'content' => $content,
            'status' => AiChatMessageStatus::Completed,
        ]);

        $assistantMessage = AiChatMessage::create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant,
            'content' => '',
            'status' => AiChatMessageStatus::Pending,
        ]);

        try {
            $contents = $this->promptBuilder->build($conversation);

            $baseSystemPrompt = trim(
                (string) config('ai-chat.system_prompt', '')
            );

            $learningContext = $this->contextBuilder->build($conversation);

            $systemPrompt = trim(
                $baseSystemPrompt
                ."\n\n学習コンテキスト:\n"
                .$learningContext
            );

            $result = $this->geminiClient->generate(
                $contents,
                $systemPrompt
            );

            $assistantMessage->update([
                'content' => $result['content'],
                'status' => AiChatMessageStatus::Completed,
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'response_time_ms' => $result['response_time_ms'],
            ]);
        } catch (Throwable $e) {
            $assistantMessage->update([
                'status' => AiChatMessageStatus::Error,
                'error_detail' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (
            (bool) config('ai-chat.auto_title_enabled', true)
            && $conversation->title === '新しい相談'
        ) {
            $completedAssistantCount = $conversation->messages()
                ->where('role', AiChatMessageRole::Assistant->value)
                ->where('status', AiChatMessageStatus::Completed->value)
                ->count();

            if ($completedAssistantCount === 1) {
                try {
                    $title = $this->generateTitle(
                        $content,
                        $assistantMessage->content
                    );

                    if ($title !== '') {
                        $conversation->update([
                            'title' => mb_substr($title, 0, 100),
                        ]);
                    }
                } catch (GeminiApiException $e) {
                    // タイトル生成失敗は通常回答の成功に影響させない
                }
            }
        }

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage->fresh(),
        ];
    }

    private function generateTitle(
        string $question,
        string $answer
    ): string {
        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => "質問:\n{$question}\n\n回答:\n{$answer}",
                    ],
                ],
            ],
        ];

        $systemPrompt = <<<'PROMPT'
    この会話の内容を表す短い日本語タイトルを1つだけ作成してください。
    タイトル以外の説明・引用符・記号は付けないでください。
    100文字以内にしてください。
    PROMPT;

        $result = $this->geminiClient->generate(
            $contents,
            $systemPrompt
        );

        return trim($result['content']);
    }
}
