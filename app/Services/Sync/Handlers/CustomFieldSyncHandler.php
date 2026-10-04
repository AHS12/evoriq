<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Entity\CustomFieldSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;

/**
 * Ingests custom-field definitions (ENT-09) from
 * `GET /workspaces/{ws}/custom-fields`. Type/entity-type/status are stored as
 * strings (Clockify's enum set is only partially documented, so unknown values
 * fall back gracefully); `allowedValues` are kept for Select fields. Values
 * attached to entries/users are separate specs (ENT-10/11).
 */
class CustomFieldSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::CUSTOM_FIELDS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return CustomFieldSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/custom-fields";
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'description' => $this->string($raw['description'] ?? null),
            'type' => $this->string($raw['type'] ?? null) ?? 'TEXT',
            'entity_type' => $this->string($raw['entityType'] ?? null) ?? 'TIMEENTRY',
            'status' => $this->string($raw['status'] ?? null),
            'required' => (bool) ($raw['required'] ?? false),
            'only_admin_can_edit' => (bool) ($raw['onlyAdminCanEdit'] ?? false),
            'allowed_values' => $this->list($raw['allowedValues'] ?? null),
            'placeholder' => $this->string($raw['placeholder'] ?? null),
            'workspace_default_value' => $raw['workspaceDefaultValue'] ?? null,
            'project_default_values' => $raw['projectDefaultValues'] ?? null,
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function list(mixed $value): ?array
    {
        return is_array($value) ? array_values($value) : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
