<?php

namespace Database\Factories;

use App\Models\ClockifyCustomField;
use App\Models\ClockifyUser;
use App\Models\ClockifyUserCustomFieldValue;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyUserCustomFieldValue>
 */
class ClockifyUserCustomFieldValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyUserCustomFieldValue>
     */
    protected $model = ClockifyUserCustomFieldValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'user_id' => ClockifyUser::factory(),
            'custom_field_id' => ClockifyCustomField::factory(),
            'value' => fake()->word(),
            'raw_data' => null,
        ];
    }
}
