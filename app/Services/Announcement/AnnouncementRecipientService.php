<?php

declare(strict_types=1);

namespace App\Services\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class AnnouncementRecipientService
{
    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(
        AnnouncementTargetType $targetType,
        ?string $targetCertificationId,
        ?string $targetUserId,
    ): Collection {
        return $this->queryFor(
            $targetType,
            $targetCertificationId,
            $targetUserId,
        )->get();
    }

    public function countFor(
        AnnouncementTargetType $targetType,
        ?string $targetCertificationId,
        ?string $targetUserId,
    ): int {
        return $this->queryFor(
            $targetType,
            $targetCertificationId,
            $targetUserId,
        )->count();
    }

    /**
     * @return Builder<User>
     */
    private function queryFor(
        AnnouncementTargetType $targetType,
        ?string $targetCertificationId,
        ?string $targetUserId,
    ): Builder {
        return match ($targetType) {
            AnnouncementTargetType::AllStudents => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value),

            AnnouncementTargetType::Certification => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->whereHas('enrollments', function ($query) use ($targetCertificationId) {
                    $query->where('certification_id', $targetCertificationId);
                }),

            AnnouncementTargetType::User => User::query()
                ->whereKey($targetUserId)
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value),
        };
    }
}
