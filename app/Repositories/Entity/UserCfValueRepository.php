<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyCustomField;
use App\Models\ClockifyUser;
use App\Models\ClockifyUserCustomFieldValue;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\UserCfValueRepositoryInterface;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * All data access for user custom-field values (ENT-11). The set is
 * authoritative per user, so each sync deletes the user's rows and inserts the
 * resolved set; unknown custom fields are skipped.
 */
class UserCfValueRepository implements UserCfValueRepositoryInterface
{
    public function syncForUser(ClockifyWorkspace $workspace, ClockifyUser $user, array $values): int
    {
        ClockifyUserCustomFieldValue::query()
            ->withoutOrganizationScope()
            ->where('user_id', $user->id)
            ->delete();

        if ($values === []) {
            return 0;
        }

        $fieldClockifyIds = [];

        foreach ($values as $value) {
            $fieldId = $value['custom_field_clockify_id'] ?? null;

            if (is_string($fieldId) && $fieldId !== '') {
                $fieldClockifyIds[$fieldId] = true;
            }
        }

        $fields = $this->resolveFieldIds($workspace, array_keys($fieldClockifyIds));

        $written = 0;

        foreach ($values as $value) {
            $fieldId = $fields[(string) ($value['custom_field_clockify_id'] ?? '')] ?? null;

            if ($fieldId === null) {
                continue;
            }

            ClockifyUserCustomFieldValue::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'custom_field_id' => $fieldId,
                    'value' => $value['value'] ?? null,
                    'raw_data' => $value['raw_data'] ?? $value,
                ]);

            $written++;
        }

        return $written;
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolveFieldIds(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $fields = ClockifyCustomField::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($fields as $field) {
            $map[(string) $field->getAttribute('clockify_id')] = (int) $field->getKey();
        }

        return $map;
    }
}
