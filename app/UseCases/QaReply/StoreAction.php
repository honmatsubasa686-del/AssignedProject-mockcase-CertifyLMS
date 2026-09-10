<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Enums\UserStatus;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(
        QaThread $thread,
        User $user,
        array $validated
    ): QaReply {
        return DB::transaction(function () use ($thread, $user, $validated) {
            $reply = QaReply::create([
                'qa_thread_id' => $thread->id,
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);

            $recipient = $thread->user;

            if (
                $recipient !== null
                && $recipient->id !== $user->id
                && $recipient->status === UserStatus::InProgress
            ) {
                DB::afterCommit(function () use ($recipient, $thread, $reply): void {
                    $recipient->notify(
                        new QaReplyReceivedNotification($thread, $reply)
                    );
                });
            }

            return $reply;
        });
    }
}
