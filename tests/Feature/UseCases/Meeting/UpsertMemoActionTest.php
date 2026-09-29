<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_meeting_memo(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(UpsertMemoAction::class)(
            $meeting,
            'Actionから保存した面談メモ',
        );

        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => 'Actionから保存した面談メモ',
        ]);
    }

    public function test_updates_existing_meeting_memo(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        app(UpsertMemoAction::class)(
            $meeting,
            '初回メモ',
        );

        app(UpsertMemoAction::class)(
            $meeting,
            '更新後メモ',
        );

        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '更新後メモ',
        ]);

        $this->assertDatabaseCount('meeting_memos', 1);
    }

    public function test_rejects_canceled_meeting(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->create();

        $this->expectException(
            MeetingStatusTransitionException::class,
        );

        app(UpsertMemoAction::class)(
            $meeting,
            '保存できないメモ',
        );
    }
}
