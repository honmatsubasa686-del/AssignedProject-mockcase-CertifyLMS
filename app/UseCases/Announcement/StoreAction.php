<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Jobs\SendAnnouncementNotificationsJob;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementRecipientService;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    public function __construct(
        private readonly AnnouncementRecipientService $recipientService,
    ) {}

    public function __invoke(User $admin, array $validated): Announcement
    {
        return DB::transaction(function () use ($admin, $validated) {
            $targetType = AnnouncementTargetType::from($validated['target_type']);

            $recipientCount = $this->recipientService->CountFor(
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
                'dispatched_count' => $recipientCount,
                'dispatched_at' => now(),
            ]);

            DB::afterCommit(function () use ($announcement): void {
                SendAnnouncementNotificationsJob::dispatch($announcement);
            });

            return $announcement;
        });
    }
}
