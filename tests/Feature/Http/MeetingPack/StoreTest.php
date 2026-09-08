<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '5 回パック',
                'description' => 'テスト用の面談パックです。',
                'meeting_count' => 5,
                'price' => 12000,
                'stripe_price_id' => null,
                'sort_order' => 10,
            ]);

        $plan = MeetingPack::query()->where('name', '5 回パック')->firstOrFail();

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertSame(MeetingPackStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_meeting_count_must_be_between_one_and_one_hundred(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => 'テストパック',
                'description' => null,
                'meeting_count' => 101,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('meeting_count');
    }

    public function test_price_must_not_exceed_one_million(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => 'テストパック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 1000001,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_sort_order_must_be_zero_or_greater(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => 'テストパック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors('sort_order');
    }

    public function test_student_cannot_create_meeting_pack(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->post(route('admin.meeting-packs.store'), [
                'name' => 'テストパック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_create_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->post(route('admin.meeting-packs.store'), [
                'name' => 'テストパック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 3000,
                'stripe_price_id' => null,
                'sort_order' => 0,
            ]);

        $response->assertForbidden();
    }
}
