<?php

namespace App\Http\Requests\Setting;

use App\Enums\AppLocale;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocaleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'in:'.implode(',', array_column(AppLocale::cases(), 'value'))],
        ];
    }
}
