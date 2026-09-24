<?php

namespace App\Repositories\Contracts;

use App\DTOs\Notification\NotificationFeedFilterDTO;
use App\Models\Notification;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    /**
     * Paginated, addressable feed for a user.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    public function feedFor(int $userId, NotificationFeedFilterDTO $filters): LengthAwarePaginator;

    /**
     * The number of addressable, unread, non-expired notifications.
     */
    public function unreadCountFor(int $userId): int;

    /**
     * The latest addressable notifications for a user.
     *
     * @return Collection<int, Notification>
     */
    public function recentFor(int $userId, int $limit): Collection;

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?Notification;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Notification;

    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    public function createTargets(Notification $notification, array $targets): void;

    public function deleteTargets(Notification $notification): void;

    public function delete(Notification $notification): bool;

    /**
     * Mark a notification as read for a user.
     *
     * @return bool True when the read row was newly created (was unread).
     */
    public function markRead(int $notificationId, int $userId): bool;

    /**
     * Mark every addressable unread notification as read.
     *
     * @return int The number of notifications marked as read.
     */
    public function markAllRead(int $userId, CarbonInterface $timestamp): int;

    /**
     * Hide a notification from a user's feed (per-user dismiss).
     */
    public function dismiss(int $notificationId, int $userId): void;

    /**
     * Whether the notification is addressed to the user.
     */
    public function isAddressable(int $notificationId, int $userId): bool;

    /**
     * Resolve the ids of every user a notification is addressed to.
     *
     * @return Collection<int, int>
     */
    public function resolveTargetUserIds(Notification $notification): Collection;

    /**
     * Delete expired/old notifications.
     *
     * @return int The number of notifications deleted.
     */
    public function pruneExpired(int $retentionDays, int $chunk = 500): int;

    /**
     * The addressable feed query for a user.
     *
     * @return Builder<Notification>
     */
    public function buildFeedQuery(int $userId, NotificationFeedFilterDTO $filters): Builder;
}
