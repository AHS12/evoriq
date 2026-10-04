<?php

namespace Database\Factories;

use App\Models\ClockifyCustomField;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyCustomField>
 */
class ClockifyCustomFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyCustomField>
     */
    protected $model = ClockifyCustomField::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'clockify_id' => (string) Str::uuid(),
            'name' => fake()->unique()->word(),
            'description' => null,
            'type' => 'TEXT',
            'entity_type' => 'TIMEENTRY',
            'status' => 'VISIBLE',
            'required' => false,
            'only_admin_can_edit' => false,
            'allowed_values' => null,
            'placeholder' => null,
            'workspace_default_value' => null,
            'project_default_values' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
