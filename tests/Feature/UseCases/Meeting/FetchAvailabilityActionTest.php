<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_formatted_availability_slots(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(Carbon::MONDAY)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $result = app(FetchAvailabilityAction::class)(
            $enrollment,
            '2026-10-05',
        );

        $this->assertSame('2026-10-05', $result['date']);
        $this->assertCount(3, $result['slots']);

        $this->assertSame(
            Carbon::parse('2026-10-05 09:00:00')->toIso8601String(),
            $result['slots'][0]['slot_start'],
        );

        $this->assertSame(
            Carbon::parse('2026-10-05 10:00:00')->toIso8601String(),
            $result['slots'][0]['slot_end'],
        );

        $this->assertSame(
            1,
            $result['slots'][0]['available_coach_count'],
        );
    }
}
