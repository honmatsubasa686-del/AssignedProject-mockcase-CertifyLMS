<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_fetch_notifications(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertUnauthorized();
    }

    public function test_user_can_fetch_only_own_latest_twenty_notifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        for ($i = 1; $i <= 21; $i++) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test_notification',
                'data' => [
                    'title' => '自分宛通知',
                    'message' => "通知{$i}",
                    'url' => '/notifications',
                ],
                'read_at' => null,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [
                'title' => '他人宛通知',
                'message' => '見えてはいけない通知',
                'url' => '/notifications',
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(20, 'notifications')
            ->assertJsonMissing([
                'title' => '他人宛通知',
            ])
            ->assertJsonPath('unread_count', 21);
    }

    public function test_notification_response_is_formatted_for_popover(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [
                'title' => 'テスト通知',
                'message' => '本文です。',
                'url' => '/notifications',
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('notifications.0.id', $notification->id)
            ->assertJsonPath('notifications.0.title', 'テスト通知')
            ->assertJsonPath('notifications.0.message', '本文です。')
            ->assertJsonPath('notifications.0.url', '/notifications')
            ->assertJsonPath('notifications.0.is_unread', true);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [],
            'read_at' => null,
        ]);

        $this->actingAs($student)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('notification_id', $notification->id)
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $notification = $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [],
            'read_at' => null,
        ]);

        $this->actingAs($student)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        for ($i = 0; $i < 2; $i++) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test_notification',
                'data' => [],
                'read_at' => null,
            ]);
        }

        $this->actingAs($student)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $student->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_does_not_modify_other_users_notifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [],
            'read_at' => null,
        ]);

        $otherStudent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [],
            'read_at' => null,
        ]);

        $this->actingAs($student)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $student->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $otherStudent->fresh()->unreadNotifications()->count());
    }

    public function test_notification_without_url_falls_back_to_notifications_index(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test_notification',
            'data' => [
                'title' => 'URLなし通知',
                'message' => 'フォールバック確認です。',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($student)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath(
                'notifications.0.url',
                route('notifications.index'),
            );
    }
}
