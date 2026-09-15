<?php

declare(strict_types=1);

namespace App\Console\Commands\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Console\Command;

class SendMeetingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-meeting-reminders {--window=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '予約済み面談のリマインダー通知を送信する';

    /**
     * Execute the console command.
     */
    public function handle(SendMeetingReminderAction $action): int
    {
        $window = MeetingReminderWindow::tryFrom(
            (string) $this->option('window')
        );

        if ($window === null) {
            $this->error('window は eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        if ($window === MeetingReminderWindow::Eve) {
            $start = now()->addDay()->startOfDay();
            $end = now()->addDay()->endOfDay();

            $meetings = Meeting::query()
                ->where('status', MeetingStatus::Reserved->value)
                ->whereBetween('scheduled_at', [$start, $end])
                ->get();

            foreach ($meetings as $meeting) {
                $action($meeting, $window);
            }
        }

        if ($window === MeetingReminderWindow::OneHourBefore) {
            $start = now()->addHour();
            $end = now()->addHour()->addMinutes(5);

            $meetings = Meeting::query()
                ->where('status', MeetingStatus::Reserved->value)
                ->where('scheduled_at', '>=', $start)
                ->where('scheduled_at', '<', $end)
                ->get();

            foreach ($meetings as $meeting) {
                $action($meeting, $window);
            }
        }

        return self::SUCCESS;
    }
}
