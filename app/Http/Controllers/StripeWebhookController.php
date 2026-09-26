<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Stripe\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        StripeWebhookService $stripeWebhookService,
    ): JsonResponse {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $webhookSecret = (string) config('services.stripe.webhook_secret');

        if ($webhookSecret === '') {
            return response()->json([
                'message' => 'Stripe webhook secret is not configured.',
            ], 500);
        }

        if ($signature === '') {
            return response()->json([
                'message' => 'Stripe signature is missing.',
            ], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json([
                'message' => 'Invalid Stripe webhook.',
            ], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $decodedPayload = json_decode($payload, true);

            $sessionData = $decodedPayload['data']['object'] ?? null;

            if (is_array($sessionData)) {
                $stripeWebhookService->handleCheckoutCompleted($sessionData);
            }
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
