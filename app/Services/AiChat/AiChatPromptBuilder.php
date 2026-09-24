<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;

class AiChatPromptBuilder
{
    public function build(AiChatConversation $conversation): array
    {
        $historyLimit = (int) config('ai-chat.history_limit', 20);

        $messages = $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($historyLimit)
            ->get()
            ->reverse()
            ->values();

        $contents = [];

        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message->role === AiChatMessageRole::User
                    ? 'user'
                    : 'model',
                'parts' => [
                    [
                        'text' => $message->content,
                    ],
                ],
            ];
        }

        return $contents;
    }
}
