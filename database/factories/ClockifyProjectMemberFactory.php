<?php

namespace Database\Factories;

use App\Enums\ClockifyMembershipType;
use App\Models\ClockifyProject;
use App\Models\ClockifyProjectMember;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyProjectMember>
 */
class ClockifyProjectMemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyProjectMember>
     */
    protected $model = ClockifyProjectMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'project_id' => ClockifyProject::factory(),
            'user_id' => ClockifyUser::factory(),
            'membership_type' => ClockifyMembershipType::PROJECT->value,
            'membership_status' => 'ACTIVE',
            'hourly_rate_amount' => null,
            'hourly_rate_currency' => null,
            'cost_rate_amount' => null,
            'cost_rate_currency' => null,
            'raw_data' => null,
        ];
    }
}
