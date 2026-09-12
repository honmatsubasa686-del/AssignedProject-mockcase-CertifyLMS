<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StoreAction
{
    public function __invoke(User $user, UploadedFile $file): void
    {
        $oldAvatarUrl = $user->avatar_url;

        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "avatars/{$ulid}.{$ext}";

        try {
            Storage::disk('public')->putFileAs(
                'avatars',
                $file,
                "{$ulid}.{$ext}",
            );

            $user->update([
                'avatar_url' => '/storage/'.$path,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($oldAvatarUrl !== null && str_starts_with($oldAvatarUrl, '/storage/')) {
            $oldPath = ltrim(
                str_replace('/storage/', '', $oldAvatarUrl),
                '/'
            );

            Storage::disk('public')->delete($oldPath);
        }
    }
}
