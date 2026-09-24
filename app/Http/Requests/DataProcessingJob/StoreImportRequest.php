<?php

namespace App\Http\Requests\DataProcessingJob;

use App\Enums\DataEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
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
            'entity_type' => ['required', Rule::enum(DataEntity::class)],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:20480'],
            'filters' => ['sometimes', 'array'],
            'filters.send_invitations' => ['sometimes', 'boolean'],
            'filters.default_role' => ['sometimes', 'nullable', 'string', 'exists:roles,name'],
        ];
    }
}
