<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MeetingCanceledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_array_has_expected_notification_data(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $notification = new MeetingCanceledNotification($meeting);

        $data = $notification->toArray($student);

        $this->assertSame('meeting_canceled', $data['notification_type']);
        $this->assertSame('面談がキャンセルされました。', $data['title']);
        $this->assertSame(
            $meeting->scheduled_at->format('Y/m/d H:i').' の面談がキャンセルされました。',
            $data['message']
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $data['url']
        );
    }

    public function test_to_mail_has_expected_subject_and_action_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $notification = new MeetingCanceledNotification($meeting);

        $mail = $notification->toMail($student);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 面談がキャンセルされました',
            $mail->subject
        );
        $this->assertSame(
            route('meetings.show', $meeting),
            $mail->actionUrl
        );
    }
}
