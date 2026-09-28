<?php

namespace Database\Factories;

use App\Enums\PipelineEventLevel;
use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Models\PipelineEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineEvent>
 */
class PipelineEventFactory extends Factory
{
    protected $model = PipelineEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_type' => PipelineRunType::DATA_PROCESSING,
            'run_id' => (string) fake()->numberBetween(1, 1000),
            'sequence' => fake()->numberBetween(1, 100),
            'type' => PipelineEventType::INFO,
            'level' => PipelineEventLevel::INFO,
            'stage' => null,
            'message' => fake()->sentence(),
            'context' => null,
            'progress' => null,
            'attempt' => 1,
            'duration_ms' => null,
            'occurred_at' => now(),
        ];
    }

    /**
     * A progress event carrying a percentage.
     */
    public function progress(int $processed, int $total): static
    {
        return $this->state(fn (): array => [
            'type' => PipelineEventType::PROGRESS,
            'progress' => [
                'total' => $total,
                'processed' => $processed,
                'percentage' => $total > 0 ? round($processed / $total * 100, 2) : 0.0,
            ],
        ]);
    }
}
