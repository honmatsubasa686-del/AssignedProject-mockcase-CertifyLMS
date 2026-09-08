<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_meeting_pack_without_changing_status(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->published()->create([
            'name' => '変更前パック',
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => '変更後パック',
                'description' => '説明を更新しました。',
                'meeting_count' => 5,
                'price' => 12000,
                'stripe_price_id' => null,
                'sort_order' => 20,
                'status' => MeetingPackStatus::Draft->value,
            ]);

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $plan->refresh();

        $this->assertSame('変更後パック', $plan->name);
        $this->assertSame(MeetingPackStatus::Published, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_name_is_required_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_meeting_count_must_be_between_one_and_one_hundred_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create();

        $response = $this->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => 'テストパック',
                'description' => null,
                'meeting_count' => 101,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('meeting_count');
    }

    public function test_student_cannot_update_meeting_pack(): void
    {
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->create();

        $response = $this->actingAs($student)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => '変更後パック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_update_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = MeetingPack::factory()->create();

        $response = $this->actingAs($coach)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => '変更後パック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }
}
