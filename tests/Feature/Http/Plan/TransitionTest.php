<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));

        $plan->refresh();

        $this->assertSame(PlanStatus::Published, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_admin_can_archive_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));

        $plan->refresh();

        $this->assertSame(PlanStatus::Archived, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_admin_can_unarchive_archived_plan_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));

        $plan->refresh();

        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_student_cannot_publish_plan(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($student)
            ->post(route('admin.plans.publish', $plan));

        $response->assertForbidden();
    }

    public function test_coach_cannot_publish_plan(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.plans.publish', $plan));

        $response->assertForbidden();
    }
}
