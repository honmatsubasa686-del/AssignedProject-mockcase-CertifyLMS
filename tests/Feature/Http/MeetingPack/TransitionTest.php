<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertSame(
            MeetingPackStatus::Published,
            $plan->fresh()->status
        );
    }

    public function test_admin_can_archive_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertSame(
            MeetingPackStatus::Archived,
            $plan->fresh()->status
        );
    }

    public function test_admin_can_unarchive_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $this->assertSame(
            MeetingPackStatus::Draft,
            $plan->fresh()->status
        );
    }
}
