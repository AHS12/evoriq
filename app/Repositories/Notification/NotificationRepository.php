<?php

namespace App\Repositories\Notification;

use App\DTOs\Notification\NotificationFeedFilterDTO;
use App\Enums\NotificationTargetType;
use App\Helpers\EloquentFilterHelper;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    /**
     * Columns that the feed may be ordered by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['created_at', 'priority'];

    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function feedFor(int $userId, NotificationFeedFilterDTO $filters): LengthAwarePaginator
    {
        return $this->buildFeedQuery($userId, $filters)->paginate($filters->perPage);
    }

    public function unreadCountFor(int $userId): int
    {
        $query = Notification::query()->active();

        $this->applyAddressability($query, $userId);
        $this->applyNotDismissed($query, $userId);

        return $query
            ->whereDoesntHave('reads', fn (Builder $q): Builder => $q->where('user_id', $userId))
            ->count();
    }

    /**
     * @return Collection<int, Notification>
     */
    public function recentFor(int $userId, int $limit): Collection
    {
        $query = Notification::query();

        $this->applyReadFlag($query, $userId);
        $this->applyAddressability($query, $userId);
        $this->applyNotDismissed($query, $userId);

        return $query
            ->active()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?Notification
    {
        return Notification::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Notification
    {
        return Notification::query()->create($data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    public function createTargets(Notification $notification, array $targets): void
    {
        $notification->targets()->createMany($targets);
    }

    public function deleteTargets(Notification $notification): void
    {
        $notification->targets()->delete();
    }

    public function delete(Notification $notification): bool
    {
        return (bool) $notification->delete();
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        $read = NotificationRead::query()->firstOrCreate(
            ['notification_id' => $notificationId, 'user_id' => $userId],
            ['read_at' => now()],
        );

        return $read->wasRecentlyCreated;
    }

    public function dismiss(int $notificationId, int $userId): void
    {
        $read = NotificationRead::query()->firstOrCreate(
            ['notification_id' => $notificationId, 'user_id' => $userId],
            ['read_at' => now()],
        );

        if ($read->dismissed_at === null) {
            $read->update(['dismissed_at' => now()]);
        }
    }

    public function markAllRead(int $userId, CarbonInterface $timestamp): int
    {
        $query = Notification::query()->active();

        $this->applyAddressability($query, $userId);
        $this->applyNotDismissed($query, $userId);

        $ids = $query
            ->whereDoesntHave('reads', fn (Builder $q): Builder => $q->where('user_id', $userId))
            ->pluck('id');

        $now = now();

        $ids->chunk(500)->each(function (Collection $chunk) use ($userId, $timestamp, $now): void {
            NotificationRead::query()->insertOrIgnore(
                $chunk->map(fn (int $id): array => [
                    'notification_id' => $id,
                    'user_id' => $userId,
                    'read_at' => $timestamp,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });

        return $ids->count();
    }

    public function isAddressable(int $notificationId, int $userId): bool
    {
        $query = Notification::query();

        $this->applyAddressability($query, $userId);

        return $query->whereKey($notificationId)->exists();
    }

    /**
     * @return Collection<int, int>
     */
    public function resolveTargetUserIds(Notification $notification): Collection
    {
        $notification->loadMissing('targets');

        $ids = collect();

        foreach ($notification->targets as $target) {
            $ids = match ($target->target_type) {
                NotificationTargetType::ALL => $ids->merge(User::query()->pluck('id')),
                NotificationTargetType::USER => $target->target_id !== null
                    ? $ids->push($target->target_id)
                    : $ids,
                NotificationTargetType::ROLE => $ids->merge(
                    User::query()
                        ->whereHas('roles', fn (Builder $q): Builder => $q->where('roles.id', $target->target_id))
                        ->pluck('id'),
                ),
            };
        }

        return $ids->filter()->unique()->values();
    }

    public function pruneExpired(int $retentionDays, int $chunk = 500): int
    {
        $cutoff = now()->subDays($retentionDays);
        $total = 0;

        Notification::query()
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('expires_at')->where('expires_at', '<', now());
                })->orWhere('created_at', '<', $cutoff);
            })
            ->select('id')
            ->chunkById($chunk, function (Collection $notifications) use (&$total): void {
                $ids = $notifications->pluck('id')->all();

                Notification::query()->whereIn('id', $ids)->delete();

                $total += count($ids);
            });

        return $total;
    }

    /**
     * @return Builder<Notification>
     */
    public function buildFeedQuery(int $userId, NotificationFeedFilterDTO $filters): Builder
    {
        $query = Notification::query();

        $this->applyReadFlag($query, $userId);
        $this->applyAddressability($query, $userId);
        $this->applyNotDismissed($query, $userId);

        $query->active();

        if ($filters->unreadOnly) {
            $query->whereDoesntHave('reads', fn (Builder $q): Builder => $q->where('user_id', $userId));
        }

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['title', 'body'],
            ['priority' => $filters->priority?->value],
            $query,
        );

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection)->orderByDesc('id');
    }

    /**
     * Select a per-user `is_read` flag onto the notifications query.
     *
     * @param  Builder<Notification>  $query
     */
    private function applyReadFlag(Builder $query, int $userId): void
    {
        $query->withExists([
            'reads as is_read' => fn (Builder $q): Builder => $q->where('user_id', $userId),
        ]);
    }

    /**
     * Exclude notifications the user has dismissed.
     *
     * @param  Builder<Notification>  $query
     */
    private function applyNotDismissed(Builder $query, int $userId): void
    {
        $query->whereDoesntHave('reads', fn (Builder $q): Builder => $q
            ->where('user_id', $userId)
            ->whereNotNull('dismissed_at'));
    }

    /**
     * Restrict a notifications query to rows addressed to the user.
     *
     * @param  Builder<Notification>  $query
     */
    private function applyAddressability(Builder $query, int $userId): void
    {
        $roleIds = $this->roleIdsFor($userId);

        $query->whereHas('targets', function (Builder $targets) use ($userId, $roleIds): void {
            $targets->where(function (Builder $targets) use ($userId, $roleIds): void {
                $targets->where('target_type', NotificationTargetType::ALL->value)
                    ->orWhere(function (Builder $query) use ($userId): void {
                        $query->where('target_type', NotificationTargetType::USER->value)
                            ->where('target_id', (string) $userId);
                    })
                    ->orWhere(function (Builder $query) use ($roleIds): void {
                        $query->where('target_type', NotificationTargetType::ROLE->value)
                            ->whereIn('target_id', $roleIds);
                    });
            });
        });
    }

    /**
     * The role ids held by a user, as strings (to match the string column).
     *
     * @return array<int, string>
     */
    private function roleIdsFor(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        return $user->roles()
            ->pluck('roles.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }
}
