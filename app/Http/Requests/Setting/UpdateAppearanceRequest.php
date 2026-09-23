<?php

namespace App\Http\Requests\Setting;

use App\Enums\AppearanceContrast;
use App\Enums\AppearanceMode;
use App\Enums\AppearanceTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(AppearanceMode::class)],
            'theme' => ['required', Rule::enum(AppearanceTheme::class)],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'contrast' => ['required', Rule::enum(AppearanceContrast::class)],
            'silent' => ['sometimes', 'boolean'],
        ];
    }
}
