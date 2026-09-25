<?php

namespace App\Services\Audit;

use App\DTOs\AuditLog\AuditLogFilterDTO;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Models\AuditActivity;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for the audit trail: records explicit (named) audit events and
 * serves the read side of the audit log UI.
 *
 * Model churn is collected automatically via the LogsActivity trait; record()
 * captures the transitions a human might one day dispute — role changes,
 * suspensions, setting updates, finished exports/imports.
 * Never call activity() directly from controllers or jobs.
 */
class AuditLogService
{
    public function __construct(
        protected AuditLogRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AuditEvent $event,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
        AuditLogName $channel = AuditLogName::DOMAIN,
        ?string $description = null,
    ): void {
        if (! config('activitylog.enabled', true)) {
            return;
        }

        $log = activity($channel->value)->event($event->value);

        if ($subject !== null) {
            $log->performedOn($subject);
        }

        if ($actor !== null) {
            $log->causedBy($actor);
        }

        $log->withProperties($properties)->log($description ?? $event->label());
    }

    /**
     * Keyset-paginated listing for the audit log UI.
     *
     * @return CursorPaginator<int, AuditActivity>
     */
    public function paginate(AuditLogFilterDTO $filters, ?string $cursor = null): CursorPaginator
    {
        return $this->repository->cursorPaginate($filters, $filters->perPage, $cursor);
    }

    public function find(int $id): ?AuditActivity
    {
        return $this->repository->findById($id, ['causer', 'subject']);
    }

    /**
     * Prune every channel per its configured retention window.
     */
    public function prune(): void
    {
        DB::transaction(function (): void {
            foreach (AuditLogName::cases() as $channel) {
                $this->repository->deleteOlderThan(
                    $channel->value,
                    now()->subDays($channel->retentionDays()),
                );
            }
        });
    }
}
