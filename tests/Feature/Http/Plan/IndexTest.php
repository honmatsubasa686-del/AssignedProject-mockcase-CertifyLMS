<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_index(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertViewHas('plans');
    }

    public function test_keyword_filters_by_name(): void
    {
        $admin = User::factory()->admin()->create();

        $matched = Plan::factory()->create([
            'name' => '短期集中プラン',
        ]);

        $unmatched = Plan::factory()->create([
            'name' => 'じっくり学習プラン',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'keyword' => '短期',
            ]));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertTrue($plans->contains($matched));
        $this->assertFalse($plans->contains($unmatched));
    }

    public function test_status_filters_plans(): void
    {
        $admin = User::factory()->admin()->create();

        $draft = Plan::factory()->draft()->create();
        $published = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'status' => 'draft',
            ]));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertTrue($plans->contains($draft));
        $this->assertFalse($plans->contains($published));
    }

    public function test_plans_are_paginated_by_twenty(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->count(21)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertSame(20, $plans->count());
        $this->assertSame(21, $plans->total());
    }

    public function test_index_includes_linked_student_count(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        User::factory()->student()->create([
            'plan_id' => $plan->id,
        ]);

        User::factory()->student()->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();

        $plans = $response->viewData('plans');
        $listedPlan = $plans->firstWhere('id', $plan->id);

        $this->assertSame(2, $listedPlan->users_count);
    }

    public function test_student_cannot_view_plan_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.plans.index'));

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_plan_index(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.plans.index'));

        $response->assertForbidden();
    }
}
