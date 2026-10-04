<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyProject;
use App\Models\ClockifyProjectMember;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyProjectMemberRepositoryInterface;

/**
 * All data access for project memberships (ENT-04). A project's member set is
 * authoritative, so each sync deletes the project's rows and inserts the
 * incoming set — removals are reflected and re-runs never duplicate.
 */
class ClockifyProjectMemberRepository implements ClockifyProjectMemberRepositoryInterface
{
    public function syncForProject(
        ClockifyWorkspace $workspace,
        ClockifyProject $project,
        array $members,
    ): int {
        ClockifyProjectMember::query()
            ->withoutOrganizationScope()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->delete();

        $written = 0;

        foreach ($members as $member) {
            ClockifyProjectMember::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'project_id' => $project->id,
                    'user_id' => $member['user_id'],
                    'membership_type' => $member['membership_type'] ?? 'project',
                    'membership_status' => $member['membership_status'] ?? null,
                    'hourly_rate_amount' => $member['hourly_rate_amount'] ?? null,
                    'hourly_rate_currency' => $member['hourly_rate_currency'] ?? null,
                    'cost_rate_amount' => $member['cost_rate_amount'] ?? null,
                    'cost_rate_currency' => $member['cost_rate_currency'] ?? null,
                    'raw_data' => $member['raw_data'] ?? $member,
                ]);

            $written++;
        }

        return $written;
    }
}
