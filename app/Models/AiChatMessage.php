<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'ai_chat_conversation_id',
        'role',
        'content',
        'status',
        'error_detail',
        'model',
        'input_tokens',
        'output_tokens',
        'response_time_ms',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
    ];

    protected static function booted(): void
    {
        static::created(function (AiChatMessage $message): void {
            AiChatConversation::query()
                ->where('id', $message->ai_chat_conversation_id)
                ->update([
                    'last_message_at' => $message->created_at,
                ]);
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            AiChatConversation::class,
            'ai_chat_conversation_id'
        );
    }
}
