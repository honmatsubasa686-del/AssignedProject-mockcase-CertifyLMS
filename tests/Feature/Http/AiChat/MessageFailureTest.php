<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\GeminiApiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AiChat\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MessageFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_message_remains_and_assistant_is_marked_error_when_gemini_fails(): void
    {
        config()->set('ai-chat.enabled', true);

        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->learning()
            ->create();

        $student->update([
            'default_enrollment_id' => $enrollment->id,
        ]);

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => null,
            'title' => '失敗テスト',
            'last_message_at' => now(),
        ]);

        $gemini = Mockery::mock(GeminiClient::class);

        $gemini
            ->shouldReceive('generate')
            ->once()
            ->andThrow(
                new GeminiApiException(
                    'Gemini API request failed.',
                    503
                )
            );

        $this->app->instance(
            GeminiClient::class,
            $gemini
        );

        $response = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => '再送テストです。',
                ]
            );

        $response->assertStatus(502);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => '再送テストです。',
            'status' => AiChatMessageStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
        ]);

        $this->assertSame(
            2,
            AiChatMessage::query()
                ->where('ai_chat_conversation_id', $conversation->id)
                ->count()
        );
    }
}
