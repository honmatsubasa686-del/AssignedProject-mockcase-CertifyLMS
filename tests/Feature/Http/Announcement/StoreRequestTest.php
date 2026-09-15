<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Certification;
use App\Enums\UserStatus;
use App\Enums\AnnouncementTargetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_does_not_require_target_ids(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '全体向けお知らせ',
                'body' => '全受講生向けのお知らせです。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $response->assertSessionHasNoErrors();
    }

    public function test_certification_target_requires_target_certification_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '資格指定のお知らせ',
                'body' => '資格指定のお知らせです。',
                'target_type' => AnnouncementTargetType::Certification->value,
            ]);

        // Assert
        $response->assertSessionHasErrors([
            'target_certification_id',
        ]);
    }

    public function test_user_target_requires_target_user_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'ユーザー指定のお知らせ',
                'body' => 'ユーザー指定のお知らせです。',
                'target_type' => AnnouncementTargetType::User->value,
            ]);

        // Assert
        $response->assertSessionHasErrors([
            'target_user_id',
        ]);
    }

    public function test_user_target_rejects_graduated_student(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $graduatedStudent = User::factory()->student()->create([
            'status' => UserStatus::Graduated,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'ユーザー指定のお知らせ',
                'body' => 'ユーザー指定のお知らせです。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $graduatedStudent->id,
            ]);

        // Assert
        $response->assertSessionHasErrors([
            'target_user_id',
        ]);
    }

    public function test_user_target_rejects_coach(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'ユーザー指定のお知らせ',
                'body' => 'ユーザー指定のお知らせです。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $coach->id,
            ]);

        // Assert
        $response->assertSessionHasErrors([
            'target_user_id',
        ]);
    }

    public function test_user_target_accepts_in_progress_student(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'ユーザー指定のお知らせ',
                'body' => 'ユーザー指定のお知らせです。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $student->id,
            ]);

        // Assert
        $response->assertSessionHasNoErrors();
    }

    public function test_certification_target_accepts_existing_certification(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '資格指定のお知らせ',
                'body' => '資格指定のお知らせです。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ]);

        // Assert
        $response->assertSessionHasNoErrors();
    }
}