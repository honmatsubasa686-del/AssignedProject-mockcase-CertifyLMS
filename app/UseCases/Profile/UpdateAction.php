<?php

declare(strict_types=1);

namespace App\UseCases\Profile;

use App\Enums\UserRole;
use App\Models\User;

final class UpdateAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(User $user, array $data): void
    {
        $updateData = [
            'name' => $data['name'],
            'bio' => $data['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach) {
            $updateData['meeting_url'] = $data['meeting_url'] ?? null;
        }

        $user->update($updateData);
    }
}
