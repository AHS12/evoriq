<?php

namespace Database\Factories;

use App\Enums\SyncEntityType;
use App\Models\ClockifyDeletedEntity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyDeletedEntity>
 */
class ClockifyDeletedEntityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyDeletedEntity>
     */
    protected $model = ClockifyDeletedEntity::class;

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
            'deleted_at' => now(),
            'document_code' => null,
            'applied_at' => null,
            'raw_data' => null,
        ];
    }

    /**
     * A deletion that has already been applied.
     */
    public function applied(): static
    {
        return $this->state(fn (): array => [
            'applied_at' => now(),
        ]);
    }
}
