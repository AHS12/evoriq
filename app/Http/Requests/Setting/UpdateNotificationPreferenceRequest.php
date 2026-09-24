<?php

namespace App\Http\Requests\Setting;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inapp' => ['required', 'boolean'],
            'sound' => ['required', 'boolean'],
            'desktop' => ['required', 'boolean'],
            'muted_types' => ['array'],
            'muted_types.*' => ['string', Rule::in(NotificationType::values())],
        ];
    }
}
