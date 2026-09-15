<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_to_array_has_expected_notification_data(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        $data = $notification->toArray($student);

        $this->assertSame('meeting_reminder', $data['notification_type']);
        $this->assertSame('明日の面談リマインダー', $data['title']);
        $this->assertSame(
            $meeting->scheduled_at->format('Y/m/d H:i').' の面談が明日あります。',
            $data['message']
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url']
        );
    }

    public function test_one_hour_before_to_array_has_expected_notification_data(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addHour()->startOfMinute(),
            ]);

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderWindow::OneHourBefore,
        );

        $data = $notification->toArray($student);

        $this->assertSame('meeting_reminder', $data['notification_type']);
        $this->assertSame('面談開始1時間前のお知らせ', $data['title']);
        $this->assertSame(
            $meeting->scheduled_at->format('Y/m/d H:i').' の面談が1時間後に始まります。',
            $data['message']
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url']
        );
    }

    public function test_eve_to_mail_has_expected_subject_and_action_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        $mail = $notification->toMail($student);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 明日の面談リマインダー',
            $mail->subject
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $mail->actionUrl
        );
    }

    public function test_one_hour_before_to_mail_has_expected_subject_and_action_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $notification = new MeetingReminderNotification(
            $meeting,
            MeetingReminderWindow::OneHourBefore,
        );

        $mail = $notification->toMail($student);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 面談開始1時間前のお知らせ',
            $mail->subject
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $mail->actionUrl
        );
    }
}
