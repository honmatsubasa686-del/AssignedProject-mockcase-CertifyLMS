<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingReminderActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_sends_eve_reminder_to_in_progress_student_and_coach(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(SendMeetingReminderAction::class)(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class
        );
    }

    public function test_does_not_send_duplicate_reminders_when_run_twice(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $action = app(SendMeetingReminderAction::class);

        $action(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        $studentNotificationsAfterFirstRun = Notification::sent(
            $student,
            MeetingReminderNotification::class
        )->count();

        $coachNotificationsAfterFirstRun = Notification::sent(
            $coach,
            MeetingReminderNotification::class
        )->count();

        $action(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        $this->assertSame(
            $studentNotificationsAfterFirstRun,
            Notification::sent(
                $student,
                MeetingReminderNotification::class
            )->count()
        );

        $this->assertSame(
            $coachNotificationsAfterFirstRun,
            Notification::sent(
                $coach,
                MeetingReminderNotification::class
            )->count()
        );

        $this->assertDatabaseCount('meeting_reminders', 2);
    }

    public function test_does_not_send_reminder_to_graduated_student(): void
    {
        Notification::fake();

        $student = User::factory()->student()->create([
            'status' => UserStatus::Graduated->value,
        ]);

        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(SendMeetingReminderAction::class)(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        Notification::assertNotSentTo(
            $student,
            MeetingReminderNotification::class
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class
        );
    }

    public function test_does_not_send_reminder_for_canceled_meeting(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(SendMeetingReminderAction::class)(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        Notification::assertNothingSent();

        $this->assertDatabaseCount('meeting_reminders', 0);
    }

    public function test_does_not_send_reminder_for_completed_meeting(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(SendMeetingReminderAction::class)(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        Notification::assertNothingSent();

        $this->assertDatabaseCount('meeting_reminders', 0);
    }

    public function test_records_sent_at_for_each_notification_channel(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(SendMeetingReminderAction::class)(
            $meeting,
            MeetingReminderWindow::Eve,
        );

        $reminders = MeetingReminder::query()
            ->where('meeting_id', $meeting->id)
            ->where('window', MeetingReminderWindow::Eve->value)
            ->get();

        $this->assertCount(2, $reminders);

        foreach ($reminders as $reminder) {
            $this->assertNotNull($reminder->database_sent_at);
            $this->assertNotNull($reminder->mail_sent_at);
        }
    }
}
