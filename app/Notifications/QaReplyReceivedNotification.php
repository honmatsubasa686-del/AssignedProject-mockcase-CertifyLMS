<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly QaThread $thread,
        private readonly QaReply $reply,
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
        $url = route('qa-board.show', $this->thread).'#reply-'.$this->reply->id;

        return (new MailMessage)
            ->subject('Certify LMS 質問に回答がありました')
            ->greeting($notifiable->name.'さん')
            ->line('あなたの質問に新しい回答が投稿されました。')
            ->action('回答を確認する', $url)
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
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答がありました。',
            'message' => $this->reply->body,
            'url' => route('qa-board.show', $this->thread).'#reply-'.$this->reply->id,
        ];
    }
}
