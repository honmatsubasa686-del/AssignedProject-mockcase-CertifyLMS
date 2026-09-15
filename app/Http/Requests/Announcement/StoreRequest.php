<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => [
                'required',
                Rule::enum(AnnouncementTargetType::class),
            ],

            'target_certification_id' => [
                'nullable',
                'ulid',
                'exists:certifications,id',
                Rule::requiredIf(
                    fn () => $this->input('target_type') === AnnouncementTargetType::Certification->value
                ),
            ],

            'target_user_id' => [
                'nullable',
                'ulid',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value),
                Rule::requiredIf(
                    fn () => $this->input('target_type') === AnnouncementTargetType::User->value
                ),
            ],
        ];
    }
}
