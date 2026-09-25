<?php

namespace App\DTOs\AuditLog;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use Illuminate\Http\Request;

final readonly class AuditLogFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?AuditLogName $channel = null,
        public ?AuditEvent $event = null,
        public ?string $subjectType = null,
        public ?int $causerId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 25,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            channel: $request->filled('channel') ? AuditLogName::tryFrom((string) $request->input('channel')) : null,
            event: $request->filled('event') ? AuditEvent::tryFrom((string) $request->input('event')) : null,
            subjectType: $request->string('subject_type')->toString() ?: null,
            causerId: $request->integer('causer_id') ?: null,
            dateFrom: $request->string('date_from')->toString() ?: null,
            dateTo: $request->string('date_to')->toString() ?: null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: max(1, min(
                $request->integer('per_page', (int) config('audit.pagination', 25)),
                100,
            )),
        );
    }

    /**
     * Return a copy of the filters scoped to a specific causer (or unscoped when null).
     */
    public function scopedToCauser(?int $userId): self
    {
        return new self(
            search: $this->search,
            channel: $this->channel,
            event: $this->event,
            subjectType: $this->subjectType,
            causerId: $userId,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            orderBy: $this->orderBy,
            orderDirection: $this->orderDirection,
            perPage: $this->perPage,
        );
    }

    /**
     * The query parameters echoed back to the frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'channel' => $this->channel?->value,
            'event' => $this->event?->value,
            'subject_type' => $this->subjectType,
            'causer_id' => $this->causerId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'order_by' => $this->orderBy,
            'order_direction' => $this->orderDirection,
            'per_page' => $this->perPage,
        ];
    }
}
