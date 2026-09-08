<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $validated
     */
    public function __invoke(User $student, array $validated): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'user_id' => $student->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
        ]));
    }
}
