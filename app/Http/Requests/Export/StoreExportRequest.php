<?php

namespace App\Http\Requests\Export;

use App\Enums\ExportEntity;
use App\Enums\ExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
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
            'entity_type' => ['required', Rule::enum(ExportEntity::class)],
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'filters' => ['sometimes', 'array'],
            'filters.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_items' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
