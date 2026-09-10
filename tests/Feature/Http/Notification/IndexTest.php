<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_user_can_see_only_own_notifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '自分宛の通知です。',
        ]);

        $student->notify(
            new QaReplyReceivedNotification($thread, $reply)
        );

        $otherThread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $otherReply = QaReply::factory()->create([
            'qa_thread_id' => $otherThread->id,
            'body' => '他人宛の通知です。',
        ]);

        $otherStudent->notify(
            new QaReplyReceivedNotification($otherThread, $otherReply)
        );

        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('自分宛の通知です。');
        $response->assertDontSee('他人宛の通知です。');
    }

    public function test_unread_tab_shows_only_unread_notifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $unreadReply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '未読通知です。',
        ]);

        $student->notify(
            new QaReplyReceivedNotification($thread, $unreadReply)
        );

        $readReply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '既読通知です。',
        ]);

        $student->notify(
            new QaReplyReceivedNotification($thread, $readReply)
        );

        $student->notifications()
            ->where('data->message', '既読通知です。')
            ->firstOrFail()
            ->markAsRead();

        $response = $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertSee('未読通知です。');
        $response->assertDontSee('既読通知です。');
    }

    public function test_notifications_are_paginated_twenty_per_page(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        for ($i = 1; $i <= 21; $i++) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test_notification',
                'data' => [
                    'notification_type' => 'chat_message_received',
                    'title' => 'テスト通知',
                    'message' => "通知{$i}",
                    'url' => route('notifications.index'),
                ],
                'read_at' => null,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $firstPage = $this->actingAs($student)
            ->get(route('notifications.index'));

        $firstPage->assertOk();

        $firstPage->assertViewHas(
            'notifications',
            fn ($notifications) => $notifications->count() === 20
                && $notifications->currentPage() === 1
                && $notifications->hasMorePages()
        );

        $secondPage = $this->actingAs($student)
            ->get(route('notifications.index', ['page' => 2]));

        $secondPage->assertOk();

        $secondPage->assertViewHas(
            'notifications',
            fn ($notifications) => $notifications->count() === 1
                && $notifications->currentPage() === 2
        );
    }
}
