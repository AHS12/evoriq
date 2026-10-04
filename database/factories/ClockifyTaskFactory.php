<?php

namespace Database\Factories;

use App\Models\ClockifyProject;
use App\Models\ClockifyTask;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyTask>
 */
class ClockifyTaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyTask>
     */
    protected $model = ClockifyTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'project_id' => ClockifyProject::factory(),
            'assignee_user_id' => null,
            'clockify_id' => (string) Str::uuid(),
            'name' => fake()->unique()->words(3, true),
            'status' => 'ACTIVE',
            'billable' => true,
            'estimated_hours' => null,
            'billable_rate_amount' => null,
            'billable_rate_currency' => null,
            'cost_rate_amount' => null,
            'cost_rate_currency' => null,
            'completed_at' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
