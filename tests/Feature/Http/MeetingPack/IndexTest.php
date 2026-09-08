<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_index(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertViewHas('plans');
    }

    public function test_keyword_filters_by_name(): void
    {
        $admin = User::factory()->admin()->create();

        $matched = MeetingPack::factory()->create([
            'name' => '5 回パック',
        ]);

        $unmatched = MeetingPack::factory()->create([
            'name' => '10 回パック',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'keyword' => '5 回',
            ]));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertTrue($plans->contains($matched));
        $this->assertFalse($plans->contains($unmatched));
    }

    public function test_status_filters_meeting_packs(): void
    {
        $admin = User::factory()->admin()->create();

        $draft = MeetingPack::factory()->draft()->create();
        $published = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'status' => 'draft',
            ]));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertTrue($plans->contains($draft));
        $this->assertFalse($plans->contains($published));
    }

    public function test_meeting_packs_are_paginated_by_twenty(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->count(21)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertSame(20, $plans->count());
        $this->assertSame(21, $plans->total());
    }

    public function test_student_cannot_view_meeting_pack_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_meeting_pack_index(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }
}
