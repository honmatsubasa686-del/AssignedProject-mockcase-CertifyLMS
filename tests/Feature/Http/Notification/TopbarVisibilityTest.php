<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Tests\TestCase;

final class TopbarVisibilityTest extends TestCase
{
    public function test_student_can_see_notification_bell(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertSee('data-notification-popover-trigger', false);
    }

    public function test_coach_can_see_notification_bell(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertSee('data-notification-popover-trigger', false);
    }

    public function test_admin_cannot_see_notification_bell(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)
            ->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertDontSee('data-notification-popover-trigger', false);
    }
}
