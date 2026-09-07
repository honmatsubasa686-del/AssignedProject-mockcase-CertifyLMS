<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexQaThreadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:unresolved,resolved'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'keyword' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => '解決状態',
            'certification_id' => '資格',
            'keyword' => 'キーワード',
        ];
    }
}
