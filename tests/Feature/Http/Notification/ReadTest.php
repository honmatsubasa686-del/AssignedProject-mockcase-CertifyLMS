<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_own_notification_as_read_and_redirect_to_related_page(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '既読化テストです。',
        ]);

        $student->notify(
            new QaReplyReceivedNotification($thread, $reply)
        );

        $notification = $student->notifications()->firstOrFail();

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification->id));

        $this->assertNotNull($notification->fresh()->read_at);

        $response->assertRedirect(
            route('qa-board.show', $thread).'#reply-'.$reply->id
        );
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
        ]);

        $otherStudent->notify(
            new QaReplyReceivedNotification($thread, $reply)
        );

        $notification = $otherStudent->notifications()->firstOrFail();

        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification->id));

        $response->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
