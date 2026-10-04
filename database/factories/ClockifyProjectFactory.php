<?php

namespace Database\Factories;

use App\Models\ClockifyProject;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyProject>
 */
class ClockifyProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyProject>
     */
    protected $model = ClockifyProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'client_id' => null,
            'clockify_id' => (string) Str::uuid(),
            'name' => fake()->unique()->words(3, true),
            'color' => fake()->hexColor(),
            'note' => null,
            'status' => 'ACTIVE',
            'archived' => false,
            'archived_at' => null,
            'billable' => true,
            'public' => true,
            'billable_rate_amount' => null,
            'billable_rate_currency' => null,
            'cost_rate_amount' => null,
            'cost_rate_currency' => null,
            'estimated_hours' => null,
            'estimated_cost' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
