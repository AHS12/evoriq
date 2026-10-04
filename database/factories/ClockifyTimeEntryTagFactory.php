<?php

namespace Database\Factories;

use App\Models\ClockifyTag;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryTag;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyTimeEntryTag>
 */
class ClockifyTimeEntryTagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyTimeEntryTag>
     */
    protected $model = ClockifyTimeEntryTag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'time_entry_id' => ClockifyTimeEntry::factory(),
            'tag_id' => ClockifyTag::factory(),
        ];
    }
}
