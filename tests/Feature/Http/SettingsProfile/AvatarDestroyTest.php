<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'avatars/avatar.png',
            'avatar'
        );

        $user = User::factory()->create([
            'avatar_url' => '/storage/avatars/avatar.png',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete('/settings/avatar');

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('avatars/avatar.png');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => null,
        ]);
    }

    public function test_user_can_delete_avatar_when_avatar_is_not_set(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete('/settings/avatar');

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => null,
        ]);
    }

    public function test_graduated_student_can_delete_avatar(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'avatars/avatar.png',
            'avatar'
        );

        $user = User::factory()->create([
            'status' => UserStatus::Graduated,
            'avatar_url' => '/storage/avatars/avatar.png',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete('/settings/avatar');

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('avatars/avatar.png');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_url' => null,
        ]);
    }
}
