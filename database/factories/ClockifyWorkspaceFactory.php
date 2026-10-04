<?php

namespace Database\Factories;

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyWorkspace>
 */
class ClockifyWorkspaceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyWorkspace>
     */
    protected $model = ClockifyWorkspace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => ClockifyConnection::factory(),
            'clockify_id' => (string) Str::uuid(),
            'name' => fake()->unique()->company(),
            'subdomain' => null,
            'currency' => 'USD',
            'time_zone' => 'UTC',
            'week_start' => 'MONDAY',
            'default_billable' => null,
            'default_hourly_rate' => null,
            'default_cost_rate' => null,
            'feature_subscription_type' => 'STANDARD_2021',
            'cake_organization_id' => null,
            'features' => [],
            'workspace_settings' => null,
            'active' => true,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
