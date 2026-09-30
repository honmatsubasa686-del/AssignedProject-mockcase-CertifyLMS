<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Announcement;
use App\Notifications\AnnouncementNotification;
use App\Services\Announcement\AnnouncementRecipientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendAnnouncementNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Announcement $announcement,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(
        AnnouncementRecipientService $recipientService,
    ): void {
        $announcement = $this->announcement->fresh();

        if ($announcement === null) {
            return;
        }

        $recipients = $recipientService->recipientsFor(
            $announcement->target_type,
            $announcement->target_certification_id,
            $announcement->target_user_id,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify(
                new AnnouncementNotification($announcement)
            );
        }
    }
}
