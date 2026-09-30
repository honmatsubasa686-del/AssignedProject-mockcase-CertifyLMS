<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\MeetingReminderWindow;
use App\Jobs\SendMeetingReminderNotificationJob;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingReminderNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_database_and_mail_notifications_and_records_sent_at(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();

        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $reminder = MeetingReminder::create([
            'meeting_id' => $meeting->id,
            'user_id' => $student->id,
            'window' => MeetingReminderWindow::Eve->value,
            'database_sent_at' => null,
            'mail_sent_at' => null,
        ]);

        $job = new SendMeetingReminderNotificationJob($reminder);

        $job->handle();

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels): bool {
                return $channels === ['database'];
            }
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels): bool {
                return $channels === ['mail'];
            }
        );

        $reminder->refresh();

        $this->assertNotNull($reminder->database_sent_at);
        $this->assertNotNull($reminder->mail_sent_at);
    }

    public function test_retries_only_unsent_channel(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();

        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $reminder = MeetingReminder::create([
            'meeting_id' => $meeting->id,
            'user_id' => $student->id,
            'window' => MeetingReminderWindow::Eve->value,
            'database_sent_at' => now(),
            'mail_sent_at' => null,
        ]);

        $job = new SendMeetingReminderNotificationJob($reminder);

        $job->handle();

        Notification::assertNotSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels): bool {
                return $channels === ['database'];
            }
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels): bool {
                return $channels === ['mail'];
            }
        );

        $reminder->refresh();

        $this->assertNotNull($reminder->database_sent_at);
        $this->assertNotNull($reminder->mail_sent_at);
    }

    public function test_job_has_retry_configuration(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $reminder = MeetingReminder::create([
            'meeting_id' => $meeting->id,
            'user_id' => $student->id,
            'window' => MeetingReminderWindow::Eve->value,
            'database_sent_at' => null,
            'mail_sent_at' => null,
        ]);

        $job = new SendMeetingReminderNotificationJob($reminder);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30], $job->backoff());
    }
}
