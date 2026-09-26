<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\MeetingQuota\StoreCheckoutRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingQuotaCheckoutController extends Controller
{
    public function index(): View
    {
        $plans = MeetingPack::query()
            ->published()
            ->ordered()
            ->get();

        return view('meeting-quota.checkout-select', [
            'plans' => $plans,
        ]);
    }

    public function store(
        StoreCheckoutRequest $request,
        StripeCheckoutService $stripeCheckoutService,
    ): RedirectResponse {
        $meetingPack = MeetingPack::query()
            ->published()
            ->findOrFail($request->validated('meeting_pack_id'));

        $payment = Payment::query()->create([
            'user_id' => $request->user()->id,
            'meeting_pack_id' => $meetingPack->id,
            'status' => PaymentStatus::Pending,
            'amount' => $meetingPack->price,
            'quantity' => $meetingPack->meeting_count,
            'currency' => 'jpy',
        ]);

        $session = $stripeCheckoutService->create(
            $request->user(),
            $meetingPack,
            $payment,
        );

        $payment->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return redirect()->away($session->url);
    }

    public function success(Request $request): View
    {
        $payment = Payment::query()
            ->where('id', $request->query('payment'))
            ->where('user_id', $request->user()->id)
            ->with('meetingPack')
            ->first();

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
