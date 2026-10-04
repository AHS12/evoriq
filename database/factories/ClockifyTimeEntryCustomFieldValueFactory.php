<?php

namespace Database\Factories;

use App\Models\ClockifyCustomField;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryCustomFieldValue;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyTimeEntryCustomFieldValue>
 */
class ClockifyTimeEntryCustomFieldValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyTimeEntryCustomFieldValue>
     */
    protected $model = ClockifyTimeEntryCustomFieldValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'time_entry_id' => ClockifyTimeEntry::factory(),
            'custom_field_id' => ClockifyCustomField::factory(),
            'value' => fake()->word(),
            'raw_data' => null,
        ];
    }
}
