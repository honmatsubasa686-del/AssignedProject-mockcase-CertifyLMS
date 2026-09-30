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

/**
 * @group external-api
 */
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

    public function test_user_can_retry_same_message_after_temporary_gemini_failure(): void
    {
        $gemini = Mockery::mock(GeminiClient::class);

        $gemini
            ->shouldReceive('generate')
            ->twice()
            ->andReturnUsing(
                function () {
                    static $attempt = 0;

                    $attempt++;

                    if ($attempt === 1) {
                        throw new GeminiApiException(
                            'Gemini API request failed.',
                            503
                        );
                    }

                    return [
                        'content' => '再送後の回答です。',
                        'model' => 'gemini-test',
                        'input_tokens' => 10,
                        'output_tokens' => 20,
                        'response_time_ms' => 100,
                    ];
                }
            );

        $this->app->instance(
            GeminiClient::class,
            $gemini
        );

        config()->set('ai-chat.enabled', true);
        config()->set('ai-chat.auto_title_enabled', false);

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
            'title' => '再送テスト',
            'last_message_at' => now(),
        ]);

        $firstResponse = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => '再送テストです。',
                ]
            );

        $firstResponse->assertStatus(502);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => '再送テストです。',
            'status' => AiChatMessageStatus::Completed->value,
        ]);

        $secondResponse = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => '再送テストです。',
                ]
            );

        $secondResponse->assertOk();

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'content' => '再送後の回答です。',
            'status' => AiChatMessageStatus::Completed->value,
        ]);
    }
}
