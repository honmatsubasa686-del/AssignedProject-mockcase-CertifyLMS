<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
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
        return DB::transaction(fn () => QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]));
    }
}
