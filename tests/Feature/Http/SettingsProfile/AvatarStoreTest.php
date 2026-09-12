<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->image('avatar.png');

        $response = $this
            ->actingAs($user)
            ->post('/settings/avatar', [
                'avatar' => $file,
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_url);
        $this->assertStringStartsWith('/storage/avatars/', $user->avatar_url);

        $path = ltrim(
            str_replace('/storage/', '', $user->avatar_url),
            '/'
        );

        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_new_avatar_deletes_old_avatar_file(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'avatars/old-avatar.png',
            'old-avatar'
        );

        $user = User::factory()->create([
            'avatar_url' => '/storage/avatars/old-avatar.png',
        ]);

        $file = UploadedFile::fake()->image('new-avatar.png');

        $response = $this
            ->actingAs($user)
            ->post('/settings/avatar', [
                'avatar' => $file,
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('avatars/old-avatar.png');

        $user->refresh();

        $newPath = ltrim(
            str_replace('/storage/', '', $user->avatar_url),
            '/'
        );

        Storage::disk('public')->assertExists($newPath);
    }

    public function test_graduated_student_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'status' => UserStatus::Graduated,
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->image('avatar.png');

        $response = $this
            ->actingAs($user)
            ->post('/settings/avatar', [
                'avatar' => $file,
            ]);

        $response
            ->assertRedirect('/settings/profile')
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_url);
    }

    public function test_user_cannot_upload_unsupported_avatar_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create(
            'avatar.gif',
            100,
            'image/gif',
        );

        $response = $this
            ->actingAs($user)
            ->post('/settings/avatar', [
                'avatar' => $file,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'avatar',
            ]);
    }

    public function test_user_cannot_upload_avatar_larger_than_two_megabytes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create(
            'avatar.png',
            2049,
            'image/png',
        );

        $response = $this
            ->actingAs($user)
            ->post('/settings/avatar', [
                'avatar' => $file,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'avatar',
            ]);
    }
}
