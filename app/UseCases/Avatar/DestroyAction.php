<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DestroyAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $avatarUrl = $user->avatar_url;

            $user->update([
                'avatar_url' => null,
            ]);

            if ($avatarUrl !== null && str_starts_with($avatarUrl, '/storage/')) {
                $path = ltrim(
                    str_replace('/storage/', '', $avatarUrl),
                    '/'
                );

                DB::afterCommit(
                    fn () => Storage::disk('public')->delete($path)
                );
            }
        });
    }
}
