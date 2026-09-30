<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\AnnouncementTargetType;
use App\Jobs\SendAnnouncementNotificationsJob;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\Announcement\AnnouncementRecipientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class SendAnnouncementNotificationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_notification_only_to_target_recipients(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $announcement = Announcement::factory()->create([
            'target_type' => AnnouncementTargetType::AllStudents,
            'target_certification_id' => null,
            'target_user_id' => null,
        ]);

        $job = new SendAnnouncementNotificationsJob($announcement);

        $job->handle(
            app(AnnouncementRecipientService::class)
        );

        Notification::assertSentTo(
            $student,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $coach,
            AnnouncementNotification::class
        );
    }

    public function test_job_has_retry_configuration(): void
    {
        $announcement = Announcement::factory()->create();

        $job = new SendAnnouncementNotificationsJob($announcement);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30], $job->backoff());
    }
}
