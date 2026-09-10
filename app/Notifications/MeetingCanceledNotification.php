<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingCanceledNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Meeting $meeting,
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

        return (new MailMessage)
            ->subject('Certify LMS 面談がキャンセルされました')
            ->greeting($notifiable->name.'さん')
            ->line('面談がキャンセルされました。')
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
        return [
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました。',
            'message' => $this->meeting->scheduled_at->format('Y/m/d H:i').' の面談がキャンセルされました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}
