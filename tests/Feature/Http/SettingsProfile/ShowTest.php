<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_profile_settings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response
            ->assertOk()
            ->assertViewIs('settings.profile')
            ->assertViewHas('user', $user);
    }

    public function test_graduated_student_can_view_profile_settings(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Graduated,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response
            ->assertOk()
            ->assertViewIs('settings.profile');
    }

    public function test_coach_can_view_profile_settings(): void
    {
        $coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $response = $this
            ->actingAs($coach)
            ->get('/settings/profile');

        $response
            ->assertOk()
            ->assertViewIs('settings.profile')
            ->assertViewHas('user', $coach);
    }

    public function test_admin_can_view_profile_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/settings/profile');

        $response
            ->assertOk()
            ->assertViewIs('settings.profile')
            ->assertViewHas('user', $admin);
    }
}
