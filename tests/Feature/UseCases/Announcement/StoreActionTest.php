<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Enums\AnnouncementTargetType;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_notifies_only_in_progress_students(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $activeStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $activeCoach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        app(StoreAction::class)(
            $admin,
            [
                'title' => '全受講生向けのお知らせ',
                'body' => '受講中の受講生だけに届く通知です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_certification_id' => null,
                'target_user_id' => null,
            ]
        );

        Notification::assertSentTo(
            $activeStudent,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $graduatedStudent,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $activeCoach,
            AnnouncementNotification::class
        );
    }

    public function test_certification_target_notifies_only_in_progress_students_enrolled_in_certification(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $targetCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();

        $targetStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        Enrollment::factory()->create([
            'user_id' => $targetStudent->id,
            'certification_id' => $targetCertification->id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $otherCertification->id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $graduatedStudent->id,
            'certification_id' => $targetCertification->id,
        ]);

        app(StoreAction::class)(
            $admin,
            [
                'title' => '資格指定のお知らせ',
                'body' => '指定資格の受講生向けです。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $targetCertification->id,
                'target_user_id' => null,
            ]
        );

        Notification::assertSentTo(
            $targetStudent,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $graduatedStudent,
            AnnouncementNotification::class
        );
    }

    public function test_user_target_notifies_only_specified_in_progress_student(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $targetStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        app(StoreAction::class)(
            $admin,
            [
                'title' => '個別のお知らせ',
                'body' => '指定された受講生だけに届く通知です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_certification_id' => null,
                'target_user_id' => $targetStudent->id,
            ]
        );

        Notification::assertSentTo(
            $targetStudent,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AnnouncementNotification::class
        );
    }

    public function test_stores_announcement_with_dispatch_information(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        User::factory()
            ->student()
            ->inProgress()
            ->count(2)
            ->create();

        $announcement = app(StoreAction::class)(
            $admin,
            [
                'title' => '保存確認のお知らせ',
                'body' => '保存内容を確認します。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_certification_id' => null,
                'target_user_id' => null,
            ]
        );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => '保存確認のお知らせ',
            'body' => '保存内容を確認します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 2,
        ]);

        $this->assertNotNull($announcement->dispatched_at);
    }

    public function test_all_students_target_ignores_irrelevant_target_ids(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            [
                'title' => '全体向けお知らせ',
                'body' => '全受講生向けです。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_certification_id' => $certification->id,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $this->assertNull($announcement->target_certification_id);
        $this->assertNull($announcement->target_user_id);
    }

    public function test_certification_target_ignores_irrelevant_user_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            [
                'title' => '資格指定のお知らせ',
                'body' => '資格指定のお知らせです。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $this->assertSame(
            $certification->id,
            $announcement->target_certification_id,
        );

        $this->assertNull($announcement->target_user_id);
    }

    public function test_user_target_ignores_irrelevant_certification_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        // Act
        $announcement = app(StoreAction::class)(
            $admin,
            [
                'title' => 'ユーザー指定のお知らせ',
                'body' => 'ユーザー指定のお知らせです。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_certification_id' => $certification->id,
                'target_user_id' => $student->id,
            ],
        );

        // Assert
        $this->assertNull($announcement->target_certification_id);

        $this->assertSame(
            $student->id,
            $announcement->target_user_id,
        );
    }
}