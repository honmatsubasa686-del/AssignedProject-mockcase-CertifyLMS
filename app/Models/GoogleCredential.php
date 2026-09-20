<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCredential extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'calendar_id',
        'connected_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
