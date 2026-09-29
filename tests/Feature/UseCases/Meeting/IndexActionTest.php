<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_student_own_upcoming_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();

        $own = Meeting::factory()
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

        $result = app(IndexAction::class)($student);

        $this->assertSame('upcoming', $result['filter']);
        $this->assertTrue($result['meetings']->contains('id', $own->id));
        $this->assertCount(1, $result['meetings']->items());
    }

    public function test_filters_past_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();

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

        $result = app(IndexAction::class)($student, 'past');

        $this->assertSame('past', $result['filter']);
        $this->assertTrue($result['meetings']->contains('id', $past->id));
        $this->assertCount(1, $result['meetings']->items());
    }

    public function test_all_filter_keeps_twenty_items_per_page(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();

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

        $result = app(IndexAction::class)($student, 'all');

        $this->assertSame('all', $result['filter']);
        $this->assertSame(20, $result['meetings']->perPage());
        $this->assertSame(21, $result['meetings']->total());
        $this->assertCount(20, $result['meetings']->items());
    }

    public function test_returns_remaining_meeting_quota(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 5,
            ]);

        $result = app(IndexAction::class)($student);

        $this->assertSame(5, $result['meetingsRemaining']);
    }
}
