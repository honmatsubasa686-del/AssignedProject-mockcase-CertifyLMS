<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_without_stripe_signature_is_rejected(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_test_secret',
        ]);

        $response = $this->postJson(
            route('webhooks.stripe'),
            [
                'type' => 'checkout.session.completed',
            ],
        );

        $response->assertBadRequest();

        $response->assertJson([
            'message' => 'Stripe signature is missing.',
        ]);
    }

    public function test_completed_checkout_webhook_marks_payment_succeeded_and_adds_quota(): void
    {
        $webhookSecret = 'whsec_test_secret';

        config([
            'services.stripe.webhook_secret' => $webhookSecret,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 2,
            ]);

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(3)
            ->withPrice(8000)
            ->create();

        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => 8000,
            'quantity' => 3,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => 'cs_test_completed',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_completed',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_completed',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_completed',
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'user_id' => $student->id,
                        'meeting_pack_id' => $meetingPack->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $webhookSecret,
        );

        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        $response->assertOk();

        $payment->refresh();

        $this->assertSame(
            PaymentStatus::Succeeded,
            $payment->status,
        );

        $this->assertSame(
            'pi_test_completed',
            $payment->stripe_payment_intent_id,
        );

        $this->assertNotNull($payment->paid_at);

        $this->assertSame(
            1,
            $payment->quotaTransactions()->count(),
        );

        $this->assertSame(
            5,
            app(MeetingQuotaService::class)->remaining($student),
        );
    }

    public function test_duplicate_completed_webhook_does_not_add_quota_twice(): void
    {
        $webhookSecret = 'whsec_test_secret';

        config([
            'services.stripe.webhook_secret' => $webhookSecret,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 2,
            ]);

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(1)
            ->withPrice(3000)
            ->create();

        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => 3000,
            'quantity' => 1,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => 'cs_test_duplicate',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_duplicate',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_duplicate',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_duplicate',
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'user_id' => $student->id,
                        'meeting_pack_id' => $meetingPack->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $webhookSecret,
        );

        $server = [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            $server,
            $payload,
        )->assertOk();

        $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            $server,
            $payload,
        )->assertOk();

        $payment->refresh();

        $this->assertSame(
            1,
            $payment->quotaTransactions()->count(),
        );

        $this->assertSame(
            3,
            app(MeetingQuotaService::class)->remaining($student),
        );
    }

    public function test_unexpected_webhook_event_is_ignored_safely(): void
    {
        $webhookSecret = 'whsec_test_secret';

        config([
            'services.stripe.webhook_secret' => $webhookSecret,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_unexpected',
            'object' => 'event',
            'type' => 'payment_intent.created',
            'data' => [
                'object' => [
                    'id' => 'pi_test_unexpected',
                    'object' => 'payment_intent',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $webhookSecret,
        );

        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        $response->assertOk();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_unpaid_completed_webhook_does_not_add_quota(): void
    {
        $webhookSecret = 'whsec_test_secret';

        config([
            'services.stripe.webhook_secret' => $webhookSecret,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 2,
            ]);

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(3)
            ->withPrice(8000)
            ->create();

        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => 8000,
            'quantity' => 3,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => 'cs_test_unpaid',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_unpaid',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_unpaid',
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'payment_intent' => null,
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'user_id' => $student->id,
                        'meeting_pack_id' => $meetingPack->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $webhookSecret,
        );

        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );

        $response->assertOk();

        $payment->refresh();

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->status,
        );

        $this->assertSame(
            0,
            $payment->quotaTransactions()->count(),
        );

        $this->assertSame(
            2,
            app(MeetingQuotaService::class)->remaining($student),
        );
    }

    public function test_starting_checkout_does_not_add_meeting_quota_before_webhook(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 2,
            ]);

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(3)
            ->withPrice(8000)
            ->create();

        $session = Session::constructFrom([
            'id' => 'cs_test_pending',
            'url' => 'https://checkout.stripe.test/session',
        ]);

        $this->mock(
            StripeCheckoutService::class,
            function (MockInterface $mock) use ($session): void {
                $mock->shouldReceive('create')
                    ->once()
                    ->andReturn($session);
            }
        );

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ])
            ->assertRedirect('https://checkout.stripe.test/session');

        $payment = Payment::query()->latest()->firstOrFail();

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->status,
        );

        $this->assertSame(
            0,
            $payment->quotaTransactions()->count(),
        );

        $this->assertSame(
            2,
            app(MeetingQuotaService::class)->remaining($student),
        );
    }
}
