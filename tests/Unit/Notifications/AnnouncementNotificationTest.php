<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    public function test_via_uses_database_and_mail_channels(): void
    {
        $announcement = Announcement::factory()->create();
        $student = User::factory()->student()->inProgress()->create();

        $notification = new AnnouncementNotification($announcement);

        $this->assertSame(
            ['database', 'mail'],
            $notification->via($student)
        );
    }

    public function test_to_array_has_expected_notification_data(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'メンテナンスのお知らせ',
            'body' => '明日10時からメンテナンスを実施します。',
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $notification = new AnnouncementNotification($announcement);
        $notification->id = 'test-notification-id';

        $data = $notification->toArray($student);

        $this->assertSame('admin_announcement', $data['notification_type']);
        $this->assertSame('メンテナンスのお知らせ', $data['title']);
        $this->assertSame(
            '明日10時からメンテナンスを実施します。',
            $data['body']
        );
        $this->assertSame(
            route('notifications.show', 'test-notification-id'),
            $data['url']
        );
    }

    public function test_to_mail_has_expected_subject_and_action_url(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => '重要なお知らせ',
            'body' => '本文です。',
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $notification = new AnnouncementNotification($announcement);
        $notification->id = 'test-notification-id';

        $mail = $notification->toMail($student);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 重要なお知らせ',
            $mail->subject
        );
        $this->assertSame(
            route('notifications.show', 'test-notification-id'),
            $mail->actionUrl
        );
    }
}