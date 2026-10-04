<?php

namespace App\Http\Requests\Sync;

use App\Enums\SyncEntityType;
use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectImportRequest extends FormRequest
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
            'range_start' => ['required', 'date'],
            'range_end' => ['required', 'date', 'after_or_equal:range_start'],
            'mode' => ['sometimes', 'nullable', Rule::enum(SyncMode::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(SyncPriority::class)],
            'entities' => ['sometimes', 'array'],
            'entities.*' => [Rule::enum(SyncEntityType::class)],
        ];
    }
}
