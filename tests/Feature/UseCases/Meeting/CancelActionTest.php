<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

final class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_meeting_refunds_quota_and_notifies_recipient(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $result = app(CancelAction::class)(
            $meeting,
            $student,
        );

        $this->assertSame(
            MeetingStatus::Canceled,
            $result->status,
        );

        $this->assertSame(
            $student->id,
            $result->canceled_by_user_id,
        );

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);

        Notification::assertSentTo(
            $coach,
            MeetingCanceledNotification::class,
        );
    }

    public function test_rejects_meeting_that_has_already_started(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->subHour(),
            ]);

        $this->expectException(
            MeetingAlreadyStartedException::class,
        );

        app(CancelAction::class)(
            $meeting,
            $student,
        );
    }

    public function test_rejects_meeting_that_is_not_reserved(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $this->expectException(
            MeetingStatusTransitionException::class,
        );

        app(CancelAction::class)(
            $meeting,
            $student,
        );
    }

    /**
     * @group external-api
     */
    public function test_deletes_google_calendar_event(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $googleCalendar = Mockery::mock(GoogleCalendarService::class);

        $googleCalendar
            ->shouldReceive('deleteMeetingEvent')
            ->once();

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar,
        );

        app(CancelAction::class)(
            $meeting,
            $student,
        );
    }

    public function test_coach_cancel_notifies_student(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        app(CancelAction::class)(
            $meeting,
            $coach,
        );

        Notification::assertSentTo(
            $student,
            MeetingCanceledNotification::class,
        );
    }
}
