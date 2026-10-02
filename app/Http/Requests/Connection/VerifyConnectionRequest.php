<?php

namespace App\Http\Requests\Connection;

use App\Enums\ApiRegion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyConnectionRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['required', 'string', 'max:255'],
            'addon_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['required', Rule::enum(ApiRegion::class)],
        ];
    }
}
