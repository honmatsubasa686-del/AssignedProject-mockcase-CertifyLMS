<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly ChatRoom $room,
        private readonly ChatMessage $message,
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
        $url = route('chat.show', $this->room);

        return (new MailMessage)
            ->subject('Certify LMS 新しいチャットメッセージがあります')
            ->greeting($notifiable->name.'さん')
            ->line('新しいメッセージが届きました。')
            ->action('チャットを確認する', $url)
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
            'notification_type' => 'chat_message_received',
            'title' => '新しいチャットメッセージがあります。',
            'message' => $this->message->body,
            'url' => route('chat.show', $this->room),
        ];
    }
}
