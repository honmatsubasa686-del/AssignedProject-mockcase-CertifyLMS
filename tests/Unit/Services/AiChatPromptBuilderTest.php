<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\AiChat\AiChatPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_uses_only_recent_completed_messages_in_chronological_order(): void
    {
        config()->set('ai-chat.history_limit', 20);

        $user = User::factory()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $user->id,
            'enrollment_id' => null,
            'section_id' => null,
            'title' => '履歴テスト',
            'last_message_at' => now(),
        ]);

        for ($i = 1; $i <= 22; $i++) {
            AiChatMessage::query()->create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => $i % 2 === 1
                    ? AiChatMessageRole::User->value
                    : AiChatMessageRole::Assistant->value,
                'content' => "message-{$i}",
                'status' => AiChatMessageStatus::Completed->value,
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        AiChatMessage::query()->create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'content' => 'pending-message',
            'status' => AiChatMessageStatus::Pending->value,
            'created_at' => now()->addSeconds(23),
            'updated_at' => now()->addSeconds(23),
        ]);

        AiChatMessage::query()->create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'content' => 'error-message',
            'status' => AiChatMessageStatus::Error->value,
            'created_at' => now()->addSeconds(24),
            'updated_at' => now()->addSeconds(24),
        ]);

        $contents = app(AiChatPromptBuilder::class)->build($conversation);

        $this->assertCount(20, $contents);

        $this->assertSame(
            'message-3',
            $contents[0]['parts'][0]['text']
        );

        $this->assertSame(
            'message-22',
            $contents[19]['parts'][0]['text']
        );

        $texts = collect($contents)
            ->pluck('parts.0.text')
            ->all();

        $this->assertNotContains('message-1', $texts);
        $this->assertNotContains('message-2', $texts);
        $this->assertNotContains('pending-message', $texts);
        $this->assertNotContains('error-message', $texts);
    }
}
