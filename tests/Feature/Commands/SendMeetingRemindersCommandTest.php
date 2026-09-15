<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_processes_reserved_meetings_scheduled_for_next_day(): void
    {
        Carbon::setTestNow('2026-09-15 18:00:00');

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $targetMeeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);

        $outsideMeeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(2)->setTime(10, 0),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve']
        )->assertExitCode(0);

        $this->assertDatabaseHas('meeting_reminders', [
            'meeting_id' => $targetMeeting->id,
            'user_id' => $student->id,
            'window' => 'eve',
        ]);

        $this->assertDatabaseMissing('meeting_reminders', [
            'meeting_id' => $outsideMeeting->id,
            'user_id' => $student->id,
            'window' => 'eve',
        ]);
    }

    public function test_one_hour_before_processes_meetings_starting_within_next_five_minutes_after_one_hour(): void
    {
        Carbon::setTestNow('2026-09-15 16:00:00');

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $targetMeeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addHour()->addMinutes(3),
            ]);

        $outsideMeeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addHour()->addMinutes(5),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'one_hour_before']
        )->assertExitCode(0);

        $this->assertDatabaseHas('meeting_reminders', [
            'meeting_id' => $targetMeeting->id,
            'user_id' => $student->id,
            'window' => 'one_hour_before',
        ]);

        $this->assertDatabaseMissing('meeting_reminders', [
            'meeting_id' => $outsideMeeting->id,
            'user_id' => $student->id,
            'window' => 'one_hour_before',
        ]);
    }

    public function test_fails_when_window_is_invalid(): void
    {
        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'hoge']
        )
            ->expectsOutput('window は eve または one_hour_before を指定してください。')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
