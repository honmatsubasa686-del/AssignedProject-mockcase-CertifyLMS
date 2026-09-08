<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Plan\UnarchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnarchiveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_plan_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->expectException(PlanInvalidTransitionException::class);

        app(UnarchiveAction::class)($plan, $admin);
    }

    public function test_published_plan_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->expectException(PlanInvalidTransitionException::class);

        app(UnarchiveAction::class)($plan, $admin);
    }
}
