<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'status',
        'amount',
        'quantity',
        'currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'integer',
        'quantity' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }

    public function quotaTransactions(): HasMany
    {
        return $this->hasMany(
            MeetingQuotaTransaction::class,
            'related_payment_id'
        );
    }
}
