<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\UseCases\Plan\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unreferenced_draft_plan_can_be_deleted(): void
    {
        $plan = Plan::factory()->draft()->create();

        app(DestroyAction::class)($plan);

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_published_plan_cannot_be_deleted(): void
    {
        $plan = Plan::factory()->published()->create();

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }

    public function test_archived_plan_cannot_be_deleted(): void
    {
        $plan = Plan::factory()->archived()->create();

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }

    public function test_draft_plan_with_current_student_cannot_be_deleted(): void
    {
        $plan = Plan::factory()->draft()->create();

        User::factory()->student()->create([
            'plan_id' => $plan->id,
        ]);

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }

    public function test_draft_plan_with_history_cannot_be_deleted(): void
    {
        $plan = Plan::factory()->draft()->create();
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        UserPlanLog::factory()->create([
            'user_id' => $student->id,
            'plan_id' => $plan->id,
            'changed_by_user_id' => $admin->id,
        ]);

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }
}
