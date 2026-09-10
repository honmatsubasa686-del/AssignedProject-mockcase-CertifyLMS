<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class ChatMessageReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic unit test example.
     */
    public function test_to_array_has_expected_notification_data(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
            'body' => '通知テスト用のチャットです。',
        ]);

        $notification = new ChatMessageReceivedNotification($room, $message);

        // Act
        $data = $notification->toArray($student);

        // Assert
        $this->assertSame('chat_message_received', $data['notification_type']);
        $this->assertSame('新しいチャットメッセージがあります。', $data['title']);
        $this->assertSame('通知テスト用のチャットです。', $data['message']);
        $this->assertSame(
            route('chat.show', $room),
            $data['url']
        );
    }

    public function test_to_mail_has_expected_subject_and_action_url(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
        ]);

        $notification = new ChatMessageReceivedNotification($room, $message);

        // Act
        $mail = $notification->toMail($student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame(
            'Certify LMS 新しいチャットメッセージがあります',
            $mail->subject
        );
        $this->assertSame(
            route('chat.show', $room),
            $mail->actionUrl
        );
    }
}
