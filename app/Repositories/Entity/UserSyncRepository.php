<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyMembershipRepositoryInterface;
use App\Repositories\Contracts\UserCfValueRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Persists the user dimension and its derived sub-facts in one pass (ENT-02 +
 * ENT-11). The handler maps each `GET /users?memberships=ALL` row to user
 * attributes plus reserved `memberships` and `custom_field_values` keys; this
 * repository writes the user and hands the extras to the membership / custom
 * field value repositories.
 */
final class UserSyncRepository extends AbstractSyncUpsertRepository
{
    public function __construct(
        protected ClockifyMembershipRepositoryInterface $memberships,
        protected UserCfValueRepositoryInterface $customFieldValues,
    ) {}

    protected function syncModel(): string
    {
        return ClockifyUser::class;
    }

    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model
    {
        [$user, $memberships, $values] = $this->split($attributes);

        $model = parent::upsertByClockifyId($workspace, $user);

        $this->syncExtras($workspace, $model, $memberships, $values);

        return $model;
    }

    public function upsertMany(ClockifyWorkspace $workspace, iterable $rows): UpsertCounts
    {
        $users = [];
        $extras = [];

        foreach ($rows as $row) {
            [$user, $memberships, $values] = $this->split($row);

            $users[] = $user;
            $extras[(string) ($user['clockify_id'] ?? '')] = [$memberships, $values];
        }

        $counts = parent::upsertMany($workspace, $users);

        foreach ($extras as $clockifyId => [$memberships, $values]) {
            if ($memberships === [] && $values === null) {
                continue;
            }

            $user = $this->syncQuery()
                ->where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where('clockify_id', $clockifyId)
                ->first();

            if ($user !== null) {
                $this->syncExtras($workspace, $user, $memberships, $values);
            }
        }

        return $counts;
    }

    /**
     * Peel the reserved `memberships`/`custom_field_values` keys off the user
     * attributes so they never reach the user table.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>|null}
     */
    private function split(array $attributes): array
    {
        $memberships = $attributes['memberships'] ?? [];
        $values = $attributes['custom_field_values'] ?? null;
        unset($attributes['memberships'], $attributes['custom_field_values']);

        return [
            $attributes,
            is_array($memberships) ? $memberships : [],
            is_array($values) ? $values : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $memberships
     * @param  array<int, array<string, mixed>>|null  $values
     */
    private function syncExtras(ClockifyWorkspace $workspace, Model $user, array $memberships, ?array $values): void
    {
        if (! $user instanceof ClockifyUser) {
            return;
        }

        if ($memberships !== []) {
            $this->memberships->syncForUser($workspace, $user, $memberships);
        }

        if ($values !== null) {
            $this->customFieldValues->syncForUser($workspace, $user, $values);
        }
    }
}
