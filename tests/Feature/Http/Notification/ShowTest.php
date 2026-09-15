<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_notification_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'seeded_notification',
            'data' => [
            'notification_type' => 'admin_announcement',
            'title' => 'テストお知らせ',
            'body' => '本文です。',
            'url' => '/notifications/dummy',
            ],
        ]);

        $response = $this->actingAs($student)
            ->get(route('notifications.show', $notification->id));

        $response->assertOk();
        $response->assertSee('テストお知らせ');
        $response->assertSee('本文です。');
    }

    public function test_user_cannot_view_other_users_notification_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $notification = $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'seeded_notification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '他人宛のお知らせ',
                'body' => '他人には見せない本文です。',
            ],
        ]);

        $response = $this->actingAs($student)
            ->get(route('notifications.show', $notification->id));

        $response->assertNotFound();
    }

    public function test_viewing_notification_marks_it_as_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'seeded_notification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '既読確認',
                'body' => '既読になるか確認します。',
            ],
            'read_at' => null,
        ]);

        $this->assertNull($notification->read_at);

        $this->actingAs($student)
            ->get(route('notifications.show', $notification->id))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}