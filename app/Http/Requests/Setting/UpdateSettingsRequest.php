<?php

namespace App\Http\Requests\Setting;

use App\Enums\SettingKey;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for the current settings group.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = (string) $this->route('group');

        $rules = [];

        foreach (SettingKey::forGroup($group) as $key) {
            $rules[$key->value] = $key->rules();
        }

        return $rules;
    }
}
