<?php

namespace App\Services\Notification;

use App\DTOs\Notification\NotificationDTO;
use App\DTOs\Notification\NotificationFeedFilterDTO;
use App\Enums\SettingKey;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Setting\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $repository,
        protected SettingService $settings,
    ) {}

    /**
     * Create a notification.
     *
     * Returns null when notifications are globally disabled. The UI picks up
     * new notifications through Inertia polling, so there is no fan-out here.
     */
    public function create(NotificationDTO $dto, ?int $createdBy = null): ?Notification
    {
        if (! $this->enabled()) {
            return null;
        }

        return DB::transaction(function () use ($dto, $createdBy): Notification {
            $data = $dto->toArray();
            $data['created_by'] = $dto->createdBy ?? $createdBy;

            $notification = $this->repository->create($data);

            $this->repository->createTargets($notification, $dto->targetsToArray());

            return $notification;
        });
    }

    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function feedFor(int $userId, NotificationFeedFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->feedFor($userId, $filters);
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->repository->unreadCountFor($userId);
    }

    /**
     * @return Collection<int, Notification>
     */
    public function recentFor(int $userId, int $limit): Collection
    {
        return $this->repository->recentFor($userId, $limit);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function find(int|string $id, array $relations = []): ?Notification
    {
        return $this->repository->findById($id, $relations);
    }

    /**
     * Mark a notification as read for its recipient.
     */
    public function markRead(Notification $notification, int $userId): void
    {
        $this->ensureAddressable($notification, $userId);

        $this->repository->markRead($notification->getKey(), $userId);
    }

    /**
     * Mark every addressable notification as read.
     */
    public function markAllRead(int $userId): void
    {
        $this->repository->markAllRead($userId, now());
    }

    /**
     * Hide a notification from the user's feed.
     */
    public function dismiss(Notification $notification, int $userId): void
    {
        $this->ensureAddressable($notification, $userId);

        $this->repository->dismiss($notification->getKey(), $userId);
    }

    /**
     * Prune notifications older than the retention window.
     */
    public function pruneExpired(int $retentionDays): int
    {
        return $this->repository->pruneExpired(
            $retentionDays,
            (int) config('notification.retention.chunk', 500),
        );
    }

    /**
     * Whether notification creation is globally enabled.
     */
    private function enabled(): bool
    {
        return (bool) $this->settings->get(SettingKey::NOTIFICATION_ENABLED);
    }

    /**
     * @throws ModelNotFoundException
     */
    private function ensureAddressable(Notification $notification, int $userId): void
    {
        if (! $this->repository->isAddressable($notification->getKey(), $userId)) {
            throw (new ModelNotFoundException)->setModel(Notification::class, [$notification->getKey()]);
        }
    }
}
