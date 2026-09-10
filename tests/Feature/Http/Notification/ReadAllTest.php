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

class ReadAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply1 = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '未読通知1',
        ]);

        $reply2 = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '未読通知2',
        ]);

        $student->notify(new QaReplyReceivedNotification($thread, $reply1));
        $student->notify(new QaReplyReceivedNotification($thread, $reply2));

        $otherThread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $otherReply = QaReply::factory()->create([
            'qa_thread_id' => $otherThread->id,
        ]);

        $otherStudent->notify(
            new QaReplyReceivedNotification($otherThread, $otherReply)
        );

        $response = $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $student->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $otherStudent->fresh()->unreadNotifications()->count());
    }
}
