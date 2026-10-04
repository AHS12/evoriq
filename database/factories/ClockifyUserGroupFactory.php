<?php

namespace Database\Factories;

use App\Models\ClockifyUserGroup;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyUserGroup>
 */
class ClockifyUserGroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyUserGroup>
     */
    protected $model = ClockifyUserGroup::class;

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
            'name' => fake()->unique()->words(2, true),
            'status' => 'ACTIVE',
            'team_managers' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
