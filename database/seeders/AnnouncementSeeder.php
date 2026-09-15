<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        $certification = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null || $student === null || $certification === null) {
            $this->command?->warn(
                'AnnouncementSeeder: admin / in_progress student / published certification が不足しています。'
            );

            return;
        }

        $allStudentsRecipients = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->get();

        foreach ($allStudentsRecipients as $recipient) {
            $recipient->notifications()
                ->where('type', 'seeded_announcement_notification')
                ->delete();
        }

        $allStudentsAnnouncement = Announcement::firstOrCreate(
            [
                'title' => '全受講生向けのお知らせ',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ],
            [
                'body' => '全受講生向けのお知らせです。',
                'target_certification_id' => null,
                'target_user_id' => null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => $allStudentsRecipients->count(),
                'dispatched_at' => now()->subDays(3),
            ],
        );

        foreach ($allStudentsRecipients as $recipient) {
            $notificationId = (string) Str::uuid();

            $recipient->notifications()->create([
                'id' => $notificationId,
                'type' => 'seeded_announcement_notification',
                'data' => [
                    'notification_type' => 'admin_announcement',
                    'title' => $allStudentsAnnouncement->title,
                    'body' => $allStudentsAnnouncement->body,
                    'url' => route('notifications.show', $notificationId),
                ],
                'read_at' => null,
                'created_at' => $allStudentsAnnouncement->dispatched_at,
                'updated_at' => $allStudentsAnnouncement->dispatched_at,
            ]);
        }

        $certificationRecipients = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereHas('enrollments', function ($query) use ($certification) {
                $query->where('certification_id', $certification->id);
            })
            ->get();

        $certificationAnnouncement = Announcement::firstOrCreate(
            [
                'title' => '資格指定のお知らせ',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ],
            [
                'body' => $certification->name.'を受講中の皆さんへのお知らせです。',
                'target_user_id' => null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => $certificationRecipients->count(),
                'dispatched_at' => now()->subDays(2),
            ],
        );

        foreach ($certificationRecipients as $recipient) {
            $notificationId = (string) Str::uuid();

            $recipient->notifications()->create([
                'id' => $notificationId,
                'type' => 'seeded_announcement_notification',
                'data' => [
                    'notification_type' => 'admin_announcement',
                    'title' => $certificationAnnouncement->title,
                    'body' => $certificationAnnouncement->body,
                    'url' => route('notifications.show', $notificationId),
                ],
                'read_at' => null,
                'created_at' => $certificationAnnouncement->dispatched_at,
                'updated_at' => $certificationAnnouncement->dispatched_at,
            ]);
        }

        $userAnnouncement = Announcement::firstOrCreate(
            [
                'title' => 'ユーザー指定のお知らせ',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $student->id,
            ],
            [
                'body' => $student->name.'さんへの個別のお知らせです。',
                'target_certification_id' => null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => 1,
                'dispatched_at' => now()->subDay(),
            ],
        );

        $userNotificationId = (string) Str::uuid();

        $student->notifications()->create([
            'id' => $userNotificationId,
            'type' => 'seeded_announcement_notification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => $userAnnouncement->title,
                'body' => $userAnnouncement->body,
                'url' => route('notifications.show', $userNotificationId),
            ],
            'read_at' => null,
            'created_at' => $userAnnouncement->dispatched_at,
            'updated_at' => $userAnnouncement->dispatched_at,
        ]);
    }
}
