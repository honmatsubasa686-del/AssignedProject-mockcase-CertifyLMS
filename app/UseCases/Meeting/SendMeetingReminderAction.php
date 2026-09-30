<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Jobs\SendMeetingReminderNotificationJob;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use Illuminate\Support\Facades\DB;

final class SendMeetingReminderAction
{
    public function __invoke(
        Meeting $meeting,
        MeetingReminderWindow $window,
    ): void {
        DB::transaction(function () use ($meeting, $window): void {
            $locked = Meeting::query()
                ->whereKey($meeting->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                return;
            }

            $recipients = [
                $locked->student,
                $locked->coach,
            ];

            foreach ($recipients as $recipient) {
                if (
                    $recipient === null
                    || $recipient->status !== UserStatus::InProgress
                ) {
                    continue;
                }

                $reminder = MeetingReminder::query()
                    ->where('meeting_id', $locked->id)
                    ->where('user_id', $recipient->id)
                    ->where('window', $window->value)
                    ->first();

                if ($reminder === null) {
                    $reminder = MeetingReminder::create([
                        'meeting_id' => $locked->id,
                        'user_id' => $recipient->id,
                        'window' => $window->value,
                        'database_sent_at' => null,
                        'mail_sent_at' => null,
                    ]);
                }

                DB::afterCommit(function () use ($reminder): void {
                    SendMeetingReminderNotificationJob::dispatch($reminder);
                });
            }
        });
    }
}
