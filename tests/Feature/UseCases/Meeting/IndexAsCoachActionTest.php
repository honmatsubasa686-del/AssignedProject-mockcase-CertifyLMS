<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_coach_own_upcoming_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $own = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($otherCoach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(4)->startOfHour(),
            ]);

        $result = app(IndexAsCoachAction::class)($coach);

        $this->assertSame('upcoming', $result['filter']);
        $this->assertTrue($result['meetings']->contains('id', $own->id));
        $this->assertCount(1, $result['meetings']->items());
    }

    public function test_filters_by_student(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $target = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($otherStudent)
            ->create([
                'scheduled_at' => now()->addDays(4)->startOfHour(),
            ]);

        $result = app(IndexAsCoachAction::class)(
            $coach,
            studentId: $student->id,
        );

        $this->assertSame($student->id, $result['studentFilter']);
        $this->assertTrue($result['meetings']->contains('id', $target->id));
        $this->assertCount(1, $result['meetings']->items());
    }

    public function test_filters_by_enrollment(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $target = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(4)->startOfHour(),
            ]);

        $result = app(IndexAsCoachAction::class)(
            $coach,
            enrollmentId: $target->enrollment_id,
        );

        $this->assertSame(
            $target->enrollment_id,
            $result['enrollmentFilter'],
        );

        $this->assertTrue(
            $result['meetings']->contains('id', $target->id),
        );

        $this->assertCount(
            1,
            $result['meetings']->items(),
        );
    }

    public function test_filters_past_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $past = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->subDays(3)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $result = app(IndexAsCoachAction::class)(
            $coach,
            filter: 'past',
        );

        $this->assertSame('past', $result['filter']);
        $this->assertTrue($result['meetings']->contains('id', $past->id));
        $this->assertCount(1, $result['meetings']->items());
    }

    public function test_all_filter_keeps_twenty_items_per_page(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        for ($i = 0; $i < 21; $i++) {
            Meeting::factory()
                ->reserved()
                ->forCoach($coach)
                ->forStudent($student)
                ->create([
                    'scheduled_at' => now()
                        ->addDays(3)
                        ->addHours($i)
                        ->startOfHour(),
                ]);
        }

        $result = app(IndexAsCoachAction::class)(
            $coach,
            filter: 'all',
        );

        $this->assertSame('all', $result['filter']);
        $this->assertSame(20, $result['meetings']->perPage());
        $this->assertSame(21, $result['meetings']->total());
        $this->assertCount(20, $result['meetings']->items());
    }
}
