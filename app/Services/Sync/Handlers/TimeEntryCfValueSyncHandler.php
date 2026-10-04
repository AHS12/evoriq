<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Entity\TimeEntryCfValueSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use RuntimeException;

/**
 * Ingests time-entry custom-field values (ENT-10) from the `customFieldValues`
 * on hydrated entries (ENT-07). There is no dedicated endpoint, so it reuses the
 * per-user entries endpoint the planner funds as its own
 * `TIME_ENTRY_CUSTOM_FIELD_VALUE` fact job. Each entry is mapped to one row with
 * its nested value set; the repository replaces the entry's values so removals
 * reflect. Unknown custom fields are skipped. Deletion cascades with the entry
 * (policy `NONE`).
 */
class TimeEntryCfValueSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::TIME_ENTRY_CUSTOM_FIELD_VALUE;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::FACT;
    }

    protected function repositoryClass(): string
    {
        return TimeEntryCfValueSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        if ($context->userId === null) {
            throw new RuntimeException('A time-entry custom field value job requires a per-user context.');
        }

        return "/workspaces/{$context->workspace->clockify_id}/user/{$context->userId}/time-entries";
    }

    protected function query(SyncContext $context, int $page): array
    {
        $query = [
            'page' => $page,
            'page-size' => $context->pageSize,
            'hydrated' => 'true',
        ];

        if ($context->rangeStart !== null) {
            $query['start'] = $context->rangeStart->toIso8601String();
        }

        if ($context->rangeEnd !== null) {
            $query['end'] = $context->rangeEnd->toIso8601String();
        }

        return $query;
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        return [
            'time_entry_clockify_id' => (string) ($raw['id'] ?? ''),
            'custom_field_values' => $this->values($raw),
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::NONE;
    }

    /**
     * `null` when the payload omits `customFieldValues` (leave the entry's
     * values untouched); an array (possibly empty) is the authoritative set.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>|null
     */
    private function values(array $raw): ?array
    {
        if (! array_key_exists('customFieldValues', $raw) || ! is_array($raw['customFieldValues'])) {
            return null;
        }

        $values = [];

        foreach ($raw['customFieldValues'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $fieldId = $this->string($entry['customFieldId'] ?? $entry['fieldId'] ?? null);

            if ($fieldId === null) {
                continue;
            }

            $values[] = [
                'custom_field_clockify_id' => $fieldId,
                'value' => $entry['value'] ?? null,
                'raw_data' => $entry,
            ];
        }

        return $values;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
