<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_chat_route_is_not_found_when_feature_is_disabled(): void
    {
        config()->set('ai-chat.enabled', false);

        $student = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
        ]);

        $response = $this
            ->actingAs($student)
            ->get('/ai-chat');

        $response->assertNotFound();
    }

    public function test_ai_chat_page_is_available_when_feature_is_enabled(): void
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

        $response = $this
            ->actingAs($student)
            ->get('/ai-chat');

        $response->assertOk();
    }
}
