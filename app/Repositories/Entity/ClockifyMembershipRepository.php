<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyMembership;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyMembershipRepositoryInterface;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * All data access for the membership/rate dimension (ENT-02). Membership rows
 * have no Clockify id of their own, so they are keyed by their target and the
 * rate's effective date; a changed rate is appended, never overwritten.
 */
class ClockifyMembershipRepository implements ClockifyMembershipRepositoryInterface
{
    public function deleteForUser(ClockifyWorkspace $workspace, string $userClockifyId): int
    {
        $user = ClockifyUser::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('clockify_id', $userClockifyId)
            ->first(['id']);

        if ($user === null) {
            return 0;
        }

        return ClockifyMembership::query()
            ->withoutOrganizationScope()
            ->where('user_id', $user->getKey())
            ->delete();
    }

    public function syncForUser(
        ClockifyWorkspace $workspace,
        ClockifyUser $user,
        array $memberships,
    ): int {
        $written = 0;

        foreach ($memberships as $membership) {
            $attributes = [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'membership_type' => (string) ($membership['membership_type'] ?? ''),
                'membership_status' => $membership['membership_status'] ?? null,
                'target_type' => $membership['target_type'] ?? null,
                'target_id' => $membership['target_id'] ?? null,
                'hourly_rate_amount' => $membership['hourly_rate_amount'] ?? null,
                'hourly_rate_currency' => $membership['hourly_rate_currency'] ?? null,
                'cost_rate_amount' => $membership['cost_rate_amount'] ?? null,
                'cost_rate_currency' => $membership['cost_rate_currency'] ?? null,
                'effective_from' => $membership['effective_from'] ?? null,
                'raw_data' => $membership['raw_data'] ?? $membership,
            ];

            ClockifyMembership::query()
                ->withoutOrganizationScope()
                ->updateOrCreate($this->key($attributes), $attributes);

            $written++;
        }

        return $written;
    }

    /**
     * The natural key: the relationship target plus the date the rate started.
     * A null `effective_from` matches the single "current" row.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function key(array $attributes): array
    {
        return [
            'organization_id' => $attributes['organization_id'],
            'workspace_id' => $attributes['workspace_id'],
            'user_id' => $attributes['user_id'],
            'membership_type' => $attributes['membership_type'],
            'target_type' => $attributes['target_type'],
            'target_id' => $attributes['target_id'],
            'effective_from' => $attributes['effective_from'],
        ];
    }
}
