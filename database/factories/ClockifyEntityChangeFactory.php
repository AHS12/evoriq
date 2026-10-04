<?php

namespace Database\Factories;

use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Models\ClockifyEntityChange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyEntityChange>
 */
class ClockifyEntityChangeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyEntityChange>
     */
    protected $model = ClockifyEntityChange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'entity_type' => SyncEntityType::TIME_ENTRY,
            'clockify_id' => (string) Str::uuid(),
            'change_type' => EntityChangeType::UPDATED,
            'source_at' => now(),
            'detected_at' => now(),
            'processed_at' => null,
            'raw_data' => null,
        ];
    }

    /**
     * A change that has already been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (): array => [
            'processed_at' => now(),
        ]);
    }
}
