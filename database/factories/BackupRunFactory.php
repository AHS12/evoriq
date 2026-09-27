<?php

namespace Database\Factories;

use App\Enums\BackupRunStatus;
use App\Enums\BackupRunTrigger;
use App\Enums\BackupRunType;
use App\Models\BackupRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupRun>
 */
class BackupRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BackupRun>
     */
    protected $model = BackupRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => BackupRunType::BACKUP,
            'status' => BackupRunStatus::PENDING,
            'trigger' => BackupRunTrigger::SCHEDULED,
            'disk' => 'backups',
        ];
    }

    /**
     * Indicate that the run is executing.
     */
    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => BackupRunStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate that the backup run completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => BackupRunStatus::COMPLETED,
            'file_name' => '2026-09-27-01-30-00.zip',
            'file_size' => 1024 * 1024 * 42,
            'exit_code' => 0,
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
            'status' => BackupRunStatus::FAILED,
            'exit_code' => 1,
            'error_message' => 'The backup process failed.',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the run was triggered manually.
     */
    public function manual(): static
    {
        return $this->state(fn (): array => [
            'trigger' => BackupRunTrigger::MANUAL,
        ]);
    }

    /**
     * Indicate that the run is a cleanup or health check.
     */
    public function ofType(BackupRunType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }
}
