<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertRedirect('/settings/profile?tab=password')
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertTrue(
            Hash::check('new-password', $user->password)
        );
    }

    public function test_user_cannot_update_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrorsIn('updatePassword', [
                'current_password',
            ]);

        $user->refresh();

        $this->assertTrue(
            Hash::check('old-password', $user->password)
        );
    }

    public function test_user_cannot_update_password_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrorsIn('updatePassword', [
                'password',
            ]);

        $user->refresh();

        $this->assertTrue(
            Hash::check('old-password', $user->password)
        );
    }

    public function test_graduated_student_can_update_password(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Graduated,
            'password' => 'old-password',
        ]);

        $response = $this
            ->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertRedirect('/settings/profile?tab=password')
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertTrue(
            Hash::check('new-password', $user->password)
        );
    }
}
