<?php

namespace Database\Factories;

use App\Models\ClockifyClient;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyClient>
 */
class ClockifyClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyClient>
     */
    protected $model = ClockifyClient::class;

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
            'name' => fake()->unique()->company(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'note' => null,
            'currency_code' => 'USD',
            'archived' => false,
            'archived_at' => null,
            'raw_data' => null,
            'synced_at' => now(),
        ];
    }

    /**
     * An archived client.
     */
    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived' => true,
            'archived_at' => now()->subMonth(),
        ]);
    }
}
