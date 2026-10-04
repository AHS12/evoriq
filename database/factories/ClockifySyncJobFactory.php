<?php

namespace Database\Factories;

use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Enums\SyncPhase;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifySyncJob>
 */
class ClockifySyncJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifySyncJob>
     */
    protected $model = ClockifySyncJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sync_run_id' => ClockifySyncRun::factory(),
            'workspace_id' => null,
            'entity_type' => SyncEntityType::TIME_ENTRY,
            'phase' => SyncPhase::FACT,
            'range_start' => now()->subMonth(),
            'range_end' => now(),
            'page' => 0,
            'page_size' => 200,
            'records_processed' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_deleted' => 0,
            'status' => SyncJobStatus::PENDING,
            'attempt' => 0,
            'checkpoint' => null,
            'last_error' => null,
            'started_at' => null,
            'completed_at' => null,
            'heartbeat_at' => null,
            'next_retry_at' => null,
        ];
    }

    /**
     * A reference-dimension job.
     */
    public function reference(SyncEntityType $entity = SyncEntityType::USER): static
    {
        return $this->state(fn (): array => [
            'entity_type' => $entity,
            'phase' => SyncPhase::REFERENCE,
        ]);
    }

    /**
     * A completed job.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => SyncJobStatus::COMPLETED,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }
}
