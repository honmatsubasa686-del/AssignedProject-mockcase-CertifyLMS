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

class DailyLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_send_is_rejected_when_daily_limit_is_reached(): void
    {
        config()->set('ai-chat.enabled', true);
        config()->set('ai-chat.daily_limit', 2);

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
            'title' => '日次上限テスト',
            'last_message_at' => now(),
        ]);

        foreach (['1件目', '2件目'] as $content) {
            AiChatMessage::query()->create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User->value,
                'content' => $content,
                'status' => AiChatMessageStatus::Completed->value,
            ]);
        }

        $response = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => '3件目',
                ]
            );

        $response
            ->assertStatus(429)
            ->assertJson([
                'message' => '本日のAI相談の送信上限に達しました。',
            ]);

        $this->assertSame(
            2,
            AiChatMessage::query()
                ->where('ai_chat_conversation_id', $conversation->id)
                ->where('role', AiChatMessageRole::User->value)
                ->count()
        );
    }

    public function test_failed_ai_request_still_counts_toward_daily_limit(): void
    {
        config()->set('ai-chat.enabled', true);
        config()->set('ai-chat.daily_limit', 1);

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
            'title' => '失敗もカウント確認',
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

        $firstResponse = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => '1件目はAI失敗',
                ]
            );

        $firstResponse->assertStatus(502);

        $secondResponse = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => '2件目',
                ]
            );

        $secondResponse
            ->assertStatus(429)
            ->assertJson([
                'message' => '本日のAI相談の送信上限に達しました。',
            ]);
    }
}
