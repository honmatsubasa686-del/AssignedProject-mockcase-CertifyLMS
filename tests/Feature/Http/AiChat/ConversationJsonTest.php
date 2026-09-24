<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AiChat\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_json_does_not_expose_internal_metadata(): void
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
            'title' => 'JSON確認用',
            'last_message_at' => now(),
        ]);

        AiChatMessage::query()->create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'content' => 'テスト回答です。',
            'status' => AiChatMessageStatus::Completed->value,
            'error_detail' => 'internal error detail',
            'model' => 'gemini-3.5-flash',
            'input_tokens' => 12,
            'output_tokens' => 34,
            'response_time_ms' => 567,
        ]);

        $response = $this
            ->actingAs($student)
            ->getJson(route('ai-chat.conversations.show', $conversation));

        $response
            ->assertOk()
            ->assertJsonStructure([
                'messages' => [
                    '*' => [
                        'role',
                        'content',
                        'status',
                    ],
                ],
            ])
            ->assertJsonMissingPath('messages.0.model')
            ->assertJsonMissingPath('messages.0.input_tokens')
            ->assertJsonMissingPath('messages.0.output_tokens')
            ->assertJsonMissingPath('messages.0.response_time_ms')
            ->assertJsonMissingPath('messages.0.error_detail');
    }

    public function test_message_send_json_does_not_expose_internal_metadata(): void
    {
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
            'title' => 'JSON送信確認',
            'last_message_at' => now(),
        ]);

        $gemini = Mockery::mock(GeminiClient::class);

        $gemini
            ->shouldReceive('generate')
            ->once()
            ->andReturn([
                'content' => 'テスト回答です。',
                'model' => 'gemini-3.5-flash',
                'input_tokens' => 12,
                'output_tokens' => 34,
                'response_time_ms' => 567,
            ]);

        $this->app->instance(
            GeminiClient::class,
            $gemini
        );

        $response = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                [
                    'content' => 'テスト質問です。',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonMissingPath('assistant_message.model')
            ->assertJsonMissingPath('assistant_message.input_tokens')
            ->assertJsonMissingPath('assistant_message.output_tokens')
            ->assertJsonMissingPath('assistant_message.response_time_ms');
    }
}
