<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Plan\ArchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_plan_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->expectException(PlanInvalidTransitionException::class);

        app(ArchiveAction::class)($plan, $admin);
    }

    public function test_archived_plan_cannot_be_archived_again(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->expectException(PlanInvalidTransitionException::class);

        app(ArchiveAction::class)($plan, $admin);
    }
}
