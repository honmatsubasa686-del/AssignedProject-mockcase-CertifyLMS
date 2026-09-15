<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderWindow $window,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('meetings.show', $this->meeting);

        $subject = match ($this->window) {
            MeetingReminderWindow::Eve => 'Certify LMS 明日の面談リマインダー',
            MeetingReminderWindow::OneHourBefore => 'Certify LMS 面談開始1時間前のお知らせ',
        };

        $message = match ($this->window) {
            MeetingReminderWindow::Eve => '明日、予約済みの面談があります。',
            MeetingReminderWindow::OneHourBefore => '予約済みの面談が1時間後に始まります。',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting($notifiable->name.'さん')
            ->line($message)
            ->action('面談を確認する', $url)
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = match ($this->window) {
            MeetingReminderWindow::Eve => '明日の面談リマインダー',
            MeetingReminderWindow::OneHourBefore => '面談開始1時間前のお知らせ',
        };

        $message = match ($this->window) {
            MeetingReminderWindow::Eve => $this->meeting->scheduled_at->format('Y/m/d H:i').' の面談が明日あります。',
            MeetingReminderWindow::OneHourBefore => $this->meeting->scheduled_at->format('Y/m/d H:i').' の面談が1時間後に始まります。',
        };

        return [
            'notification_type' => 'meeting_reminder',
            'title' => $title,
            'message' => $message,
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}
