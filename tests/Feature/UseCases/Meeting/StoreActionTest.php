<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_reserved_meeting_and_consumes_quota(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $meeting = app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            'Actionから予約',
        );

        $this->assertSame(
            MeetingStatus::Reserved,
            $meeting->status,
        );

        $this->assertSame(
            $student->id,
            $meeting->student_id,
        );

        $this->assertSame(
            $coach->id,
            $meeting->coach_id,
        );

        $this->assertNotNull(
            $meeting->meeting_quota_transaction_id,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
        );
    }

    public function test_rejects_when_meeting_quota_is_insufficient(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 0,
            ]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $this->expectException(
            InsufficientMeetingQuotaException::class,
        );

        app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            '予約できないはず',
        );
    }

    public function test_rejects_double_booking_for_same_coach_and_slot(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $otherStudent = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($otherStudent)
            ->create([
                'scheduled_at' => $scheduledAt,
            ]);

        $this->expectException(
            MeetingNoAvailableCoachException::class,
        );

        app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            '二重予約になるはず',
        );
    }

    public function test_saves_google_calendar_event_id_when_event_is_created(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $googleCalendar = Mockery::mock(GoogleCalendarService::class);

        $googleCalendar
            ->shouldReceive('createMeetingEvent')
            ->once()
            ->andReturn('google-event-123');

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar,
        );

        $meeting = app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            'Google連携あり',
        );

        $this->assertSame(
            'google-event-123',
            $meeting->google_calendar_event_id,
        );
    }

    public function test_succeeds_when_google_event_creation_fails(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()->admin()->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $googleCalendar = Mockery::mock(GoogleCalendarService::class);

        $googleCalendar
            ->shouldReceive('createMeetingEvent')
            ->once()
            ->andReturn(null);

        $this->app->instance(
            GoogleCalendarService::class,
            $googleCalendar,
        );

        $meeting = app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            'Google連携失敗でも予約',
        );

        $this->assertSame(
            MeetingStatus::Reserved,
            $meeting->status,
        );

        $this->assertNull(
            $meeting->google_calendar_event_id,
        );
    }
}
