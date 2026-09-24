<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_view_another_students_conversation(): void
    {
        config()->set('ai-chat.enabled', true);

        $owner = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $ownerEnrollment = Enrollment::factory()
            ->for($owner)
            ->learning()
            ->create();

        $owner->update([
            'default_enrollment_id' => $ownerEnrollment->id,
        ]);

        $otherStudent = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $otherEnrollment = Enrollment::factory()
            ->for($otherStudent)
            ->learning()
            ->create();

        $otherStudent->update([
            'default_enrollment_id' => $otherEnrollment->id,
        ]);

        $conversation = AiChatConversation::query()->create([
            'user_id' => $owner->id,
            'enrollment_id' => $ownerEnrollment->id,
            'section_id' => null,
            'title' => '所有者だけが見られる相談',
            'last_message_at' => now(),
        ]);

        $response = $this
            ->actingAs($otherStudent)
            ->get(route('ai-chat.conversations.show', $conversation));

        $response->assertForbidden();
    }

    public function test_student_can_view_own_conversation(): void
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
            'title' => '自分の相談',
            'last_message_at' => now(),
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
    }

    public function test_coach_cannot_access_ai_chat(): void
    {
        config()->set('ai-chat.enabled', true);

        $coach = User::factory()->create([
            'role' => UserRole::Coach->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $this
            ->actingAs($coach)
            ->get('/ai-chat')
            ->assertForbidden();
    }

    public function test_admin_cannot_access_ai_chat(): void
    {
        config()->set('ai-chat.enabled', true);

        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $this
            ->actingAs($admin)
            ->get('/ai-chat')
            ->assertForbidden();
    }

    public function test_graduated_student_cannot_access_ai_chat(): void
    {
        config()->set('ai-chat.enabled', true);

        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::Graduated->value,
        ]);

        $this
            ->actingAs($student)
            ->get('/ai-chat')
            ->assertForbidden();
    }
}
