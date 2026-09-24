<?php

namespace Database\Factories;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
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
            'entity_type' => DataEntity::USERS,
            'format' => ExportFormat::XLSX,
            'filters' => [],
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the job is an import with an uploaded source file.
     */
    public function import(): static
    {
        return $this->state(fn (): array => [
            'type' => DataProcessingJobType::IMPORT,
            'format' => ExportFormat::CSV,
            'original_file_name' => 'users.xlsx',
            'input_disk' => 'local',
            'input_path' => 'exports/imports/users.xlsx',
        ]);
    }

    /**
     * Indicate that the job is a report generation.
     */
    public function report(): static
    {
        return $this->state(fn (): array => [
            'type' => DataProcessingJobType::REPORT,
            'format' => ExportFormat::XLSX,
        ]);
    }

    /**
     * Indicate that the job is currently processing with partial progress.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => DataProcessingJobStatus::PROCESSING,
            'stage' => 'Generating file',
            'started_at' => now()->subMinute(),
            'total_items' => 100,
            'processed_items' => 42,
        ]);
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

    /**
     * Indicate that the job was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => DataProcessingJobStatus::CANCELLED,
            'cancel_requested_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
