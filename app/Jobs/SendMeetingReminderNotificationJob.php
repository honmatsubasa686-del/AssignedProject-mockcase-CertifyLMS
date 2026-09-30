<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MeetingReminder;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMeetingReminderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MeetingReminder $reminder,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reminder = $this->reminder->fresh();

        if ($reminder === null) {
            return;
        }

        if ($reminder->database_sent_at === null) {
            $reminder->user->notifyNow(
                new MeetingReminderNotification(
                    $reminder->meeting,
                    $reminder->window,
                ),
                ['database']
            );

            $reminder->update([
                'database_sent_at' => now(),
            ]);
        }

        if ($reminder->mail_sent_at === null) {
            $reminder->user->notifyNow(
                new MeetingReminderNotification(
                    $reminder->meeting,
                    $reminder->window,
                ),
                ['mail']
            );

            $reminder->update([
                'mail_sent_at' => now(),
            ]);
        }
    }
}
