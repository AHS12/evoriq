<?php

namespace Database\Factories;

use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifySyncRun>
 */
class ClockifySyncRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifySyncRun>
     */
    protected $model = ClockifySyncRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => ClockifyConnection::factory(),
            'workspace_id' => null,
            'trigger' => SyncTrigger::INITIAL_IMPORT,
            'mode' => SyncMode::INITIAL,
            'priority' => SyncPriority::NORMAL,
            'status' => SyncRunStatus::PENDING,
            'range_start' => now()->subMonth(),
            'range_end' => now(),
            'plan' => null,
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_deleted' => 0,
            'api_requests_used' => 0,
            'error_message' => null,
            'correlation_id' => (string) Str::uuid(),
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * A run that is currently executing.
     */
    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => SyncRunStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * A finished run.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => SyncRunStatus::COMPLETED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    /**
     * A run that failed.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => SyncRunStatus::FAILED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'error_message' => 'Sync failed.',
        ]);
    }
}
