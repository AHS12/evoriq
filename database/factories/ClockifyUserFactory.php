<?php

namespace Database\Factories;

use App\Enums\ClockifyUserStatus;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyUser>
 */
class ClockifyUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyUser>
     */
    protected $model = ClockifyUser::class;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => ClockifyUserStatus::ACTIVE,
            'profile_picture_url' => null,
            'timezone' => 'UTC',
            'week_start' => 'MONDAY',
            'working_days' => ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'],
            'work_capacity' => 28800,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }
}
