<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;

interface UserCfValueRepositoryInterface
{
    /**
     * Replace a user's custom-field values (ENT-11). The incoming set is
     * authoritative, so removed values disappear. Unknown custom fields are
     * skipped rather than failing.
     *
     * @param  array<int, array<string, mixed>>  $values  Each with `custom_field_clockify_id` + `value`.
     * @return int Number of value rows written.
     */
    public function syncForUser(ClockifyWorkspace $workspace, ClockifyUser $user, array $values): int;
}
