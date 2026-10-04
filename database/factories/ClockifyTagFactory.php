<?php

namespace Database\Factories;

use App\Models\ClockifyTag;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyTag>
 */
class ClockifyTagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyTag>
     */
    protected $model = ClockifyTag::class;

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
            'archived' => false,
            'archived_at' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
