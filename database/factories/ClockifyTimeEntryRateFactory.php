<?php

namespace Database\Factories;

use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryRate;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyTimeEntryRate>
 */
class ClockifyTimeEntryRateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyTimeEntryRate>
     */
    protected $model = ClockifyTimeEntryRate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'clockify_id' => null,
            'time_entry_id' => ClockifyTimeEntry::factory(),
            'user_id' => null,
            'project_id' => null,
            'task_id' => null,
            'billable_rate_amount' => null,
            'billable_rate_currency' => null,
            'cost_rate_amount' => null,
            'cost_rate_currency' => null,
            'raw_data' => null,
        ];
    }
}
