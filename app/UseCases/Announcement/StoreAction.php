<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use Illuminate\Support\Facades\DB;
use App\Models\Announcement;
use App\Notifications\AnnouncementNotification;
use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class StoreAction
{
    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(
        AnnouncementTargetType $targetType,
        ?string $targetCertificationId,
        ?string $targetUserId,
    ): Collection {
        return match ($targetType) {
            AnnouncementTargetType::AllStudents => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->get(),

            AnnouncementTargetType::Certification => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->whereHas('enrollments', function ($query) use ($targetCertificationId) {
                        $query->where('certification_id', $targetCertificationId);
                    })
                ->get(),

            AnnouncementTargetType::User => User::query()
                ->whereKey($targetUserId)
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->get(),
        };
    }

    public function __invoke(User $admin, array $validated): Announcement
    {
        return DB::transaction(function () use ($admin, $validated) {
            $targetType = AnnouncementTargetType::from($validated['target_type']);

            $recipients = $this->recipientsFor(
                $targetType,
                $validated['target_certification_id'] ?? null,
                $validated['target_user_id'] ?? null,
            );

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType,
                'target_certification_id' => $targetType === AnnouncementTargetType::Certification
                    ? $validated['target_certification_id']
                    : null,
                'target_user_id' => $targetType === AnnouncementTargetType::User
                    ? $validated['target_user_id']
                    : null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
            ]);

            DB::afterCommit(function () use ($recipients, $announcement): void {
                foreach ($recipients as $recipient) {
                    $recipient->notify(
                        new AnnouncementNotification($announcement)
                    );
                }
            });

            return $announcement;
        });
    }
}
