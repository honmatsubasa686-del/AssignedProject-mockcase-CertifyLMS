<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Announcement $announcement,
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
        return (new MailMessage)
            ->subject('Certify LMS '.$this->announcement->title)
            ->greeting($notifiable->name.'さん')
            ->line($this->announcement->body)
            ->action('お知らせを確認する', route('notifications.show', $this->id))
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
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'url' => route('notifications.show', $this->id),
        ];
    }
}
