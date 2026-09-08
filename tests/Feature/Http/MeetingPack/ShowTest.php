<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_detail(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->create([
            'name' => '詳細確認パック',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', $plan));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.show');
        $response->assertSee('詳細確認パック');
    }
}
