<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorageRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', 'in:s3,r2,custom'],
            'access_key_id' => ['required', 'string', 'max:255'],
            'secret_access_key' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:64'],
            'bucket' => ['required', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'use_path_style' => ['required', 'boolean'],
        ];
    }
}
