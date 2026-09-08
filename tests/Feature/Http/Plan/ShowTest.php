<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_detail(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()->create([
            'name' => '詳細確認プラン',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        User::factory()->student()->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
        $response->assertSee('詳細確認プラン');

        $viewPlan = $response->viewData('plan');

        $this->assertTrue($viewPlan->relationLoaded('users'));
        $this->assertTrue($viewPlan->relationLoaded('createdBy'));
        $this->assertTrue($viewPlan->relationLoaded('updatedBy'));
        $this->assertCount(1, $viewPlan->users);
    }

    public function test_student_cannot_view_plan_detail(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.plans.show', $plan));

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_plan_detail(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.plans.show', $plan));

        $response->assertForbidden();
    }

    public function test_student_cannot_view_plan_create_form(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.plans.create'));

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_plan_edit_form(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.plans.edit', $plan));

        $response->assertForbidden();
    }
}
