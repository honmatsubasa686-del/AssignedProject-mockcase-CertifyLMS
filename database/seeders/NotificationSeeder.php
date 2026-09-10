<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChatMember;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->firstOrFail();

        $student->notifications()
            ->where('type', 'seeded_notification')
            ->delete();

        $chatRoom = ChatMember::query()
            ->where('user_id', $student->id)
            ->with('chatRoom')
            ->firstOrFail()
            ->chatRoom;

        $reservedMeeting = Meeting::query()
            ->where('student_id', $student->id)
            ->where('status', 'reserved')
            ->firstOrFail();

        $canceledMeeting = Meeting::query()
            ->where('student_id', $student->id)
            ->where('status', 'canceled')
            ->firstOrFail();

        $notificationData = [
            [
                'notification_type' => 'chat_message_received',
                'title' => '新しいチャットメッセージがあります。',
                'message' => '新しいチャットメッセージが届いています。',
                'url' => route('chat.show', $chatRoom),
            ],
            [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました。',
                'message' => $reservedMeeting->scheduled_at->format('Y/m/d H:i').' の面談予約があります。',
                'url' => route('meetings.show', $reservedMeeting),
            ],
            [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました。',
                'message' => $canceledMeeting->scheduled_at->format('Y/m/d H:i').' の面談がキャンセルされました。',
                'url' => route('meetings.show', $canceledMeeting),
            ],
        ];

        for ($i = 0; $i < 24; $i++) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'seeded_notification',
                'data' => $notificationData[$i % count($notificationData)],
                'read_at' => $i % 3 === 0
                    ? now()->subMinutes($i + 1)
                    : null,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->firstOrFail();

        $coach->notifications()
            ->where('type', 'seeded_notification')
            ->delete();

        $coachChatRoom = ChatMember::query()
            ->where('user_id', $coach->id)
            ->with('chatRoom')
            ->firstOrFail()
            ->chatRoom;

        $coachReservedMeeting = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', 'reserved')
            ->firstOrFail();

        $coachCanceledMeeting = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', 'canceled')
            ->firstOrFail();

        $coachNotificationData = [
            [
                'notification_type' => 'chat_message_received',
                'title' => '新しいチャットメッセージがあります。',
                'message' => '受講生から新しいチャットメッセージが届いています。',
                'url' => route('chat.show', $coachChatRoom),
            ],
            [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました。',
                'message' => $coachReservedMeeting->scheduled_at->format('Y/m/d H:i').' の面談予約があります。',
                'url' => route('meetings.show', $coachReservedMeeting),
            ],
            [
                'notification_type' => 'meeting_canceled',
                'title' => '面談がキャンセルされました。',
                'message' => $coachCanceledMeeting->scheduled_at->format('Y/m/d H:i').' の面談がキャンセルされました。',
                'url' => route('meetings.show', $coachCanceledMeeting),
            ],
        ];

        for ($i = 0; $i < 12; $i++) {
            $coach->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'seeded_notification',
                'data' => $coachNotificationData[$i % count($coachNotificationData)],
                'read_at' => $i % 2 === 0
                    ? now()->subMinutes($i + 1)
                    : null,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }
    }
}
