<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_index(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $response->assertOk();
    }

    public function test_student_cannot_view_announcement_index(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->get(route('admin.announcements.index'));

        // Assert
        $response->assertForbidden();
    }

    public function test_guest_cannot_view_announcement_index(): void
    {
        // Act
        $response = $this->get(route('admin.announcements.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_create_shows_only_in_progress_students_as_user_targets(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $inProgressStudent = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $otherStatusStudent = User::factory()->student()->create([
            'status' => UserStatus::Graduated,
        ]);

        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $response->assertOk();

        $response->assertViewHas('students', function ($students) use ($inProgressStudent, $otherStatusStudent, $coach, ): bool {
            return $students->contains($inProgressStudent)
                && !$students->contains($otherStatusStudent)
                && !$students->contains($coach);
        });
    }

    public function test_create_shows_certifications_as_targets(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $response->assertOk();

        $response->assertViewHas('certifications', function ($certifications) use ($certification): bool {
            return $certifications->contains($certification);
        });
    }

    public function test_admin_can_store_announcement(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => 'システムメンテナンスのお知らせ',
                'body' => '明日の午前中にメンテナンスを実施します。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ],
        );

        // Assert
        $announcement = Announcement::query()->firstOrFail();

        $response->assertRedirect(
            route('admin.announcements.show', $announcement)
        );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'システムメンテナンスのお知らせ',
            'body' => '明日の午前中にメンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_view_announcement_show(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $admin->id,
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $response->assertOk();

        $response->assertViewHas(
            'announcement',
            fn($viewAnnouncement): bool => $viewAnnouncement->is($announcement)
        );
    }

    public function test_student_cannot_store_announcement(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('admin.announcements.store'), [
                'title' => '不正なお知らせ',
                'body' => 'studentからの送信です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
    }
}