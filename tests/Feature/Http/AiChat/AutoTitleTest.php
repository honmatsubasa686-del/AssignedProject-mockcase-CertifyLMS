<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AiChat\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AutoTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_successful_ai_response_generates_conversation_title(): void
    {
        config()->set('ai-chat.enabled', true);
        config()->set('ai-chat.auto_title_enabled', true);

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
            'title' => '新しい相談',
            'last_message_at' => now(),
        ]);

        $gemini = Mockery::mock(GeminiClient::class);

        $gemini
            ->shouldReceive('generate')
            ->twice()
            ->andReturn(
                [
                    'content' => '二分探索木は、左に小さい値、右に大きい値を配置する木構造です。',
                    'model' => 'gemini-3.5-flash',
                    'input_tokens' => 10,
                    'output_tokens' => 20,
                    'response_time_ms' => 500,
                ],
                [
                    'content' => '二分探索木の特徴',
                    'model' => 'gemini-3.5-flash',
                    'input_tokens' => 8,
                    'output_tokens' => 5,
                    'response_time_ms' => 300,
                ]
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
                    'content' => '二分探索木の特徴を教えてください。',
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '二分探索木の特徴',
        ]);
    }

    public function test_conversation_title_is_not_generated_when_auto_title_is_disabled(): void
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
            'title' => '新しい相談',
            'last_message_at' => now(),
        ]);

        $gemini = Mockery::mock(GeminiClient::class);

        $gemini
            ->shouldReceive('generate')
            ->once()
            ->andReturn([
                'content' => '二分探索木は、左に小さい値、右に大きい値を配置する木構造です。',
                'model' => 'gemini-3.5-flash',
                'input_tokens' => 10,
                'output_tokens' => 20,
                'response_time_ms' => 500,
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
                    'content' => '二分探索木の特徴を教えてください。',
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '新しい相談',
        ]);
    }
}
