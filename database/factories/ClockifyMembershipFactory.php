<?php

namespace Database\Factories;

use App\Enums\ClockifyMembershipType;
use App\Models\ClockifyMembership;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyMembership>
 */
class ClockifyMembershipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyMembership>
     */
    protected $model = ClockifyMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'user_id' => ClockifyUser::factory(),
            'membership_type' => ClockifyMembershipType::WORKSPACE->value,
            'membership_status' => 'ACTIVE',
            'target_type' => null,
            'target_id' => null,
            'hourly_rate_amount' => null,
            'hourly_rate_currency' => null,
            'cost_rate_amount' => null,
            'cost_rate_currency' => null,
            'effective_from' => null,
            'raw_data' => null,
        ];
    }
}
