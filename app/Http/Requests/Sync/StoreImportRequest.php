<?php

namespace App\Http\Requests\Sync;

use App\Enums\SyncEntityType;
use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
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
        $maxYears = max(1, (int) config('clockify.planner.max_history_years', 5));
        $earliest = now()->subYears($maxYears)->toDateString();

        return [
            'range_start' => ['required', 'date', "after_or_equal:{$earliest}"],
            'range_end' => ['required', 'date', 'after_or_equal:range_start', 'before_or_equal:now'],
            'mode' => ['sometimes', 'nullable', Rule::enum(SyncMode::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(SyncPriority::class)],
            'entities' => ['sometimes', 'array'],
            'entities.*' => [Rule::enum(SyncEntityType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'range_start.after_or_equal' => __('The import range cannot exceed :years years.', [
                'years' => max(1, (int) config('clockify.planner.max_history_years', 5)),
            ]),
        ];
    }

    public function rangeStart(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('range_start'));
    }

    public function rangeEnd(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('range_end'));
    }
}
