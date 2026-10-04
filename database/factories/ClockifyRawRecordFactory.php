<?php

namespace Database\Factories;

use App\Enums\SyncEntityType;
use App\Models\ClockifyRawRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyRawRecord>
 */
class ClockifyRawRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyRawRecord>
     */
    protected $model = ClockifyRawRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockifyId = (string) Str::uuid();
        $payload = ['id' => $clockifyId, 'description' => fake()->sentence()];

        return [
            'workspace_id' => null,
            'entity_type' => SyncEntityType::TIME_ENTRY,
            'clockify_id' => $clockifyId,
            'payload' => $payload,
            'payload_hash' => hash('sha256', (string) json_encode($payload)),
            'source' => 'api',
            'fetched_at' => now(),
        ];
    }
}
