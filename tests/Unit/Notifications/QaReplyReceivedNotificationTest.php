<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class QaReplyReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic unit test example.
     */
    public function test_to_array_has_expected_notification_data(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'body' => '通知テスト用の回答です。',
        ]);

        $notification = new QaReplyReceivedNotification($thread, $reply);

        // Act
        $data = $notification->toArray($student);

        // Assert
        $this->assertSame('qa_reply_received', $data['notification_type']);
        $this->assertSame('質問に回答がありました。', $data['title']);
        $this->assertSame('通知テスト用の回答です。', $data['message']);
        $this->assertSame(
            route('qa-board.show', $thread).'#reply-'.$reply->id,
            $data['url']
        );
    }

    public function test_to_mail_has_expected_subject_and_action_url(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
        ]);

        $notification = new QaReplyReceivedNotification($thread, $reply);

        // Act
        $mail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Certify LMS 質問に回答がありました', $mail->subject);
        $this->assertSame(
            route('qa-board.show', $thread).'#reply-'.$reply->id,
            $mail->actionUrl
        );
    }

    public function test_notification_is_queued_with_retry_configuration(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);
        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
        ]);

        $notification = new QaReplyReceivedNotification($thread, $reply);

        $this->assertInstanceOf(
            ShouldQueue::class,
            $notification
        );

        $this->assertSame(3, $notification->tries);
        $this->assertSame([10, 30], $notification->backoff());
    }
}
