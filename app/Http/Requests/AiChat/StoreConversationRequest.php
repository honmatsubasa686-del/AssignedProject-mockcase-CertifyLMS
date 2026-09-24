<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('create', AiChatConversation::class);
    }

    public function rules(): array
    {
        return [
            'source' => [
                'required',
                'string',
                Rule::in(['widget', 'full-screen']),
            ],
            'section_id' => [
                'nullable',
                'ulid',
                'exists:sections,id',
            ],
            'message' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
