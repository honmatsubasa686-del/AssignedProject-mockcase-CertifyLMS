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

class MeetingQuotaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_meeting_pack_cannot_be_purchased(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->draft()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertSessionHasErrors('meeting_pack_id');

        $this->assertDatabaseMissing('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
        ]);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_archived_meeting_pack_cannot_be_purchased(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->archived()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertSessionHasErrors('meeting_pack_id');

        $this->assertDatabaseMissing('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
        ]);
    }

    public function test_coach_cannot_purchase_meeting_pack(): void
    {
        $coach = User::factory()
            ->coach()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($coach)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('payments', [
            'user_id' => $coach->id,
            'meeting_pack_id' => $meetingPack->id,
        ]);
    }

    public function test_admin_cannot_purchase_meeting_pack(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($admin)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('payments', [
            'user_id' => $admin->id,
            'meeting_pack_id' => $meetingPack->id,
        ]);
    }

    public function test_graduated_student_cannot_purchase_meeting_pack(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
        ]);
    }

    public function test_active_learning_student_can_start_checkout_for_published_pack(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->withCount(3)
            ->withPrice(8000)
            ->create();

        $session = Session::constructFrom([
            'id' => 'cs_test_mock',
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

        $response = $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertRedirect('https://checkout.stripe.test/session');

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => 'pending',
            'amount' => 8000,
            'quantity' => 3,
            'currency' => 'jpy',
            'stripe_checkout_session_id' => 'cs_test_mock',
        ]);
    }

    public function test_success_page_does_not_add_meeting_quota(): void
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

        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => 8000,
            'quantity' => 3,
            'currency' => 'jpy',
        ]);

        $response = $this->actingAs($student)
            ->get(route('meeting-quota.success', [
                'payment' => $payment->id,
            ]));

        $response->assertOk();

        $response->assertViewHas(
            'payment',
            fn (Payment $viewPayment): bool => $viewPayment->is($payment),
        );

        $this->assertSame(
            2,
            app(MeetingQuotaService::class)->remaining($student),
        );

        $this->assertSame(
            0,
            $payment->quotaTransactions()->count(),
        );
    }

    public function test_student_cannot_view_another_students_payment_on_success_page(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $meetingPack = MeetingPack::factory()
            ->published()
            ->create();

        $payment = Payment::query()->create([
            'user_id' => $otherStudent->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => $meetingPack->price,
            'quantity' => $meetingPack->meeting_count,
            'currency' => 'jpy',
        ]);

        $response = $this->actingAs($student)
            ->get(route('meeting-quota.success', [
                'payment' => $payment->id,
            ]));

        $response->assertOk();

        $response->assertViewHas(
            'payment',
            fn ($viewPayment): bool => $viewPayment === null,
        );
    }
}
