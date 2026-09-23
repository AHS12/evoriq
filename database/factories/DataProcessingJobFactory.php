<?php

namespace Database\Factories;

use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportEntity;
use App\Enums\ExportFormat;
use App\Models\DataProcessingJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DataProcessingJob>
 */
class DataProcessingJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<DataProcessingJob>
     */
    protected $model = DataProcessingJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => (string) Str::uuid(),
            'type' => DataProcessingJobType::EXPORT,
            'status' => DataProcessingJobStatus::PENDING,
            'entity_type' => ExportEntity::USERS,
            'format' => ExportFormat::XLSX,
            'filters' => [],
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the job completed.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => DataProcessingJobStatus::COMPLETED,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the job failed.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => DataProcessingJobStatus::FAILED,
            'error_message' => 'Export failed',
            'completed_at' => now(),
        ]);
    }
}
