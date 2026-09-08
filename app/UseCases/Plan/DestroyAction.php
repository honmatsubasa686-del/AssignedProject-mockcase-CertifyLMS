<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    public function __invoke(Plan $plan): void
    {
        $isDeletable =
            $plan->status === PlanStatus::Draft
            && ! $plan->users()->exists()
            && ! $plan->userPlanLogs()->exists();

        if (! $isDeletable) {
            throw new PlanNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
