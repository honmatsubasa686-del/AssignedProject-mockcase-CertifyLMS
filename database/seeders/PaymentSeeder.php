<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $demoStudent = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->orderBy('created_at')
            ->first();

        $packs = MeetingPack::query()
            ->published()
            ->ordered()
            ->get();

        if ($fixedStudent === null || $demoStudent === null || $packs->count() < 3) {
            $this->command?->warn(
                'PaymentSeeder: 対象 student または published MeetingPack が不足しています。'
            );

            return;
        }

        $this->seedPaymentsForStudent(
            $fixedStudent,
            $packs[1],
            $packs[0],
            $packs[2],
            'fixed',
        );

        $this->seedPaymentsForStudent(
            $demoStudent,
            $packs[1],
            $packs[0],
            $packs[2],
            'demo',
        );
    }

    private function seedPaymentsForStudent(
        User $student,
        MeetingPack $succeededPack,
        MeetingPack $pendingPack,
        MeetingPack $failedPack,
        string $prefix,
    ): void {
        $succeededAt = now()->subDays(3);

        $succeeded = Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $succeededPack->id,
            'status' => PaymentStatus::Succeeded,
            'amount' => $succeededPack->price,
            'quantity' => $succeededPack->meeting_count,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => "cs_test_seed_{$prefix}_succeeded",
            'stripe_payment_intent_id' => "pi_test_seed_{$prefix}_succeeded",
            'paid_at' => $succeededAt,
            'created_at' => $succeededAt,
            'updated_at' => $succeededAt,
        ]);

        MeetingQuotaTransaction::query()->create([
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $succeeded->quantity,
            'related_payment_id' => $succeeded->id,
            'occurred_at' => $succeededAt,
        ]);

        Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $pendingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => $pendingPack->price,
            'quantity' => $pendingPack->meeting_count,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => "cs_test_seed_{$prefix}_pending",
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $failedPack->id,
            'status' => PaymentStatus::Failed,
            'amount' => $failedPack->price,
            'quantity' => $failedPack->meeting_count,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => "cs_test_seed_{$prefix}_failed",
            'stripe_payment_intent_id' => "pi_test_seed_{$prefix}_failed",
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }
}
