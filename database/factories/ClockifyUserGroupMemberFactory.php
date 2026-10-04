<?php

namespace Database\Factories;

use App\Models\ClockifyUser;
use App\Models\ClockifyUserGroup;
use App\Models\ClockifyUserGroupMember;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyUserGroupMember>
 */
class ClockifyUserGroupMemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyUserGroupMember>
     */
    protected $model = ClockifyUserGroupMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => ClockifyWorkspace::factory(),
            'user_group_id' => ClockifyUserGroup::factory(),
            'user_id' => ClockifyUser::factory(),
        ];
    }
}
