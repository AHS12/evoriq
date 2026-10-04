<?php

namespace Database\Factories;

use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyTimeEntry>
 */
class ClockifyTimeEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyTimeEntry>
     */
    protected $model = ClockifyTimeEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subHours(2);

        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'user_id' => ClockifyUser::factory(),
            'project_id' => null,
            'task_id' => null,
            'clockify_id' => (string) Str::uuid(),
            'description' => fake()->sentence(),
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'duration_seconds' => 3600,
            'billable' => true,
            'type' => 'REGULAR',
            'time_zone' => 'UTC',
            'is_locked' => false,
            'is_in_progress' => false,
            'approval_status' => null,
            'cost_amount' => null,
            'cost_currency' => null,
            'billable_amount' => null,
            'billable_currency' => null,
            'clockify_created_at' => null,
            'clockify_updated_at' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
