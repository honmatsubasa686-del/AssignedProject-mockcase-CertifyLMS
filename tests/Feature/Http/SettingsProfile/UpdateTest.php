<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_own_name_and_bio(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => '更新後の名前',
                'bio' => '更新後の自己紹介です。',
            ]);

        $response->assertRedirect('/settings/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '更新後の名前',
            'bio' => '更新後の自己紹介です。',
        ]);
    }

    public function test_coach_can_update_own_meeting_url(): void
    {
        $coach = User::factory()->create([
            'role' => UserRole::Coach,
            'meeting_url' => null,
        ]);

        $response = $this
            ->actingAs($coach)
            ->patch('/settings/profile', [
                'name' => $coach->name,
                'bio' => $coach->bio,
                'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            ]);

        $response->assertRedirect('/settings/profile');

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);
    }

    public function test_student_cannot_update_meeting_url(): void
    {
        $student = User::factory()->create([
            'meeting_url' => 'https://example.com/original',
        ]);

        $response = $this
            ->actingAs($student)
            ->patch('/settings/profile', [
                'name' => $student->name,
                'bio' => $student->bio,
                'meeting_url' => 'https://example.com/changed',
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'meeting_url' => 'https://example.com/original',
        ]);
    }

    public function test_email_cannot_be_updated_from_profile_settings(): void
    {
        $user = User::factory()->create([
            'email' => 'before@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => $user->name,
                'bio' => $user->bio,
                'email' => 'after@example.com',
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'before@example.com',
        ]);
    }

    public function test_graduated_student_can_update_profile(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Graduated,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => '卒業後の名前',
                'bio' => '卒業後もプロフィールは更新できます。',
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '卒業後の名前',
            'bio' => '卒業後もプロフィールは更新できます。',
        ]);
    }

    public function test_user_cannot_update_another_users_profile(): void
    {
        $user = User::factory()->create([
            'name' => '本人',
        ]);

        $otherUser = User::factory()->create([
            'name' => '他のユーザー',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'user_id' => $otherUser->id,
                'name' => '更新後の本人',
                'bio' => '本人の自己紹介',
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '更新後の本人',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => '他のユーザー',
        ]);
    }
}
