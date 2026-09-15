<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingReminderWindow;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReminder extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'window',
        'database_sent_at',
        'mail_sent_at',
    ];

    protected $casts = [
        'window' => MeetingReminderWindow::class,
        'database_sent_at' => 'datetime',
        'mail_sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
