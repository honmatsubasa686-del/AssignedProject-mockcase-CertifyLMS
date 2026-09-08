<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => '短期集中プラン',
                'description' => 'テスト用のプランです。',
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 10,
            ]);

        $plan = Plan::query()
            ->where('name', '短期集中プラン')
            ->firstOrFail();

        $response->assertRedirect(route('admin.plans.show', $plan));

        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_status_cannot_be_set_when_creating_plan(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => '公開指定プラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
                'status' => PlanStatus::Published->value,
            ]);

        $plan = Plan::query()
            ->where('name', '公開指定プラン')
            ->firstOrFail();

        $this->assertSame(PlanStatus::Draft, $plan->status);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_duration_days_must_be_between_one_and_three_thousand_six_hundred_fifty(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'テストプラン',
                'description' => null,
                'duration_days' => 3651,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('duration_days');
    }

    public function test_default_meeting_quota_must_be_between_zero_and_one_thousand(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'テストプラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 1001,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('default_meeting_quota');
    }

    public function test_sort_order_must_be_zero_or_greater(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'テストプラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors('sort_order');
    }

    public function test_student_cannot_create_plan(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->post(route('admin.plans.store'), [
                'name' => 'テストプラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_create_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.plans.store'), [
                'name' => 'テストプラン',
                'description' => null,
                'duration_days' => 30,
                'default_meeting_quota' => 4,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }
}
