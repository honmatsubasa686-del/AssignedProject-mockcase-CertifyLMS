<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_plan_basic_information(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新後プラン',
                'description' => '更新後の説明です。',
                'duration_days' => 60,
                'default_meeting_quota' => 8,
                'sort_order' => 20,
            ]);

        $response->assertRedirect(route('admin.plans.show', $plan));

        $plan->refresh();

        $this->assertSame('更新後プラン', $plan->name);
        $this->assertSame('更新後の説明です。', $plan->description);
        $this->assertSame(60, $plan->duration_days);
        $this->assertSame(8, $plan->default_meeting_quota);
        $this->assertSame(20, $plan->sort_order);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_status_cannot_be_changed_when_updating_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => $plan->name,
                'description' => $plan->description,
                'duration_days' => $plan->duration_days,
                'default_meeting_quota' => $plan->default_meeting_quota,
                'sort_order' => $plan->sort_order,
                'status' => PlanStatus::Published->value,
            ]);

        $plan->refresh();

        $this->assertSame(PlanStatus::Draft, $plan->status);
    }

    public function test_name_is_required_when_updating_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_student_cannot_update_plan(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($student)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新プラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_update_plan(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($coach)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新プラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }
}
