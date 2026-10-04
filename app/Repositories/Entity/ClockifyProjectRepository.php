<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyProject;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyProjectRepositoryInterface;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Read access to the project dimension for nested ingestion (ENT-05). Tasks are
 * fetched per project, so the handler needs the workspace's project ids.
 */
class ClockifyProjectRepository implements ClockifyProjectRepositoryInterface
{
    public function listClockifyIds(ClockifyWorkspace $workspace): array
    {
        $projects = ClockifyProject::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->get(['id', 'clockify_id']);

        $ids = [];

        foreach ($projects as $project) {
            $ids[] = (string) $project->getAttribute('clockify_id');
        }

        return $ids;
    }
}
