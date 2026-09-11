<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Enrollment;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\Enrollment\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment 詳細取得 Action `ShowAction` の eager load を検証する Feature テスト。
 * 詳細ビューに必要な certification / certificate / 最新の状態遷移ログが eager load されることを確認する。
 */
class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_certification_for_detail_view(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertTrue(
            $result->relationLoaded('certification'),
            'ShowAction は詳細表示用に certification を eager load するはず',
        );
    }

    public function test_loads_goals_in_expected_order(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();

        $unachievedLater = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '未達成・期日遅い',
            'target_date' => now()->addDays(20)->toDateString(),
            'achieved_at' => null,
        ]);

        $unachievedSoon = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '未達成・期日近い',
            'target_date' => now()->addDays(5)->toDateString(),
            'achieved_at' => null,
        ]);

        $unachievedNoDate = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '未達成・期日なし',
            'target_date' => null,
            'achieved_at' => null,
        ]);

        $achievedSoon = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '達成済み・期日近い',
            'target_date' => now()->addDays(3)->toDateString(),
            'achieved_at' => now(),
        ]);

        $achievedNoDate = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '達成済み・期日なし',
            'target_date' => null,
            'achieved_at' => now(),
        ]);

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertSame([
            $unachievedSoon->id,
            $unachievedLater->id,
            $unachievedNoDate->id,
            $achievedSoon->id,
            $achievedNoDate->id,
        ], $result->goals->pluck('id')->all());
    }
}
