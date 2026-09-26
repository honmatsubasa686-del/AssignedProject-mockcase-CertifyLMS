<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use LogicException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function create(
        User $user,
        MeetingPack $meetingPack,
        Payment $payment,
    ): Session {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new LogicException('Stripe secret Key is not configured.');
        }

        $stripe = new StripeClient($secret);

        return $stripe->checkout->sessions->create([
            'mode' => 'payment',

            'managed_payments' => [
                'enabled' => false,
            ],

            'client_reference_id' => $payment->id,

            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'jpy',
                        'unit_amount' => $payment->amount,
                        'product_data' => [
                            'name' => $meetingPack->name,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],

            'success_url' => route('meeting-quota.success', [
                'payment' => $payment->id,
            ]),

            'cancel_url' => route('meeting-quota.checkout.select'),

            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'meeting_pack_id' => $meetingPack->id,
            ],
        ]);
    }
}
