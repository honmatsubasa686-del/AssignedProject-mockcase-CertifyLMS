<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class StripeWebhookService
{
    /**
     * @param array<string, mixed> $session
     */
    public function handleCheckoutCompleted(array $session): void
    {
        $paymentId = $session['metadata']['payment_id'] ?? null;

        if (! is_string($paymentId) || $paymentId === '') {
            return;
        }

        DB::transaction(function () use ($paymentId, $session): void {
            $payment = Payment::query()
                ->lockForUpdate()
                ->find($paymentId);

            if ($payment === null) {
                return;
            }

            if ($payment->status === PaymentStatus::Succeeded) {
                return;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }

            if (($session['payment_status'] ?? null) !== 'paid') {
                return;
            }

            $paymentIntentId = $session['payment_intent'] ?? null;

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_payment_intent_id' => is_string($paymentIntentId)
                    ? $paymentIntentId
                    : null,
                'paid_at' => now(),
            ]);

            MeetingQuotaTransaction::query()->create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => now(),
            ]);
        });
    }
}
