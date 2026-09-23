<?php

namespace Database\Factories;

use App\Enums\CommandRunStatus;
use App\Models\CommandRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandRun>
 */
class CommandRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CommandRun>
     */
    protected $model = CommandRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => 'cache-clear',
            'command' => 'cache:clear',
            'status' => CommandRunStatus::PENDING,
            'progress' => 0,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the run is executing.
     */
    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => CommandRunStatus::RUNNING,
            'progress' => 50,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the run completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => CommandRunStatus::COMPLETED,
            'progress' => 100,
            'exit_code' => 0,
            'output' => 'Command completed.',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the run failed.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => CommandRunStatus::FAILED,
            'progress' => 100,
            'exit_code' => 1,
            'error_message' => 'Command failed.',
            'completed_at' => now(),
        ]);
    }
}
