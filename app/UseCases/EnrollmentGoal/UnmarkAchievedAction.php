<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人学習目標の達成状態を解除するユースケース。
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        return DB::transaction(function () use ($goal) {
            $goal->update([
                'achieved_at' => null,
            ]);

            return $goal->fresh();
        });
    }
}
