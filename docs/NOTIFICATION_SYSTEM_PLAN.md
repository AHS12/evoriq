# Plan — Evoriq Notification System (foundational)

**Status:** Implemented (DB polling) — `composer check` green (Pint, PHPStan, Pest, lint, types)
**Revision 5 (current):** SSE was **removed**. Long-lived SSE connections pinned PHP-FPM workers and slowed every page, so delivery now uses **Inertia `usePoll`** on the shared `notifications` prop (and `feed`), pausing while the tab is hidden. This is the plan's tier-3 fallback, promoted to the primary mechanism for now. SSE sections below are kept as the **deferred** design if a dedicated streaming process is ever introduced.
**Scope:** In-app notification feed + real-time delivery (SSE) + header quick view +
full feed page + retention pruning. Backend module, frontend, tests.
**Stack:** Laravel 13 · PHP 8.4 · Inertia 3 · React 19 · TypeScript · Tailwind 4 ·
shadcn/ui · PostgreSQL (SQLite in tests) · Redis **optional**
**Source:** `K:\Projects\Herd\frc-backend` notification module (multi-tenant,
DB + Redis counter, polling — no real-time in code)
**Related:** `TDR.md`, `AGENTS.md`, `.agents/rules/*`, `.agents/skills/*`,
`docs/FRC_STARTER_PORT_PLAN.md`, `docs/UI_BUILD_PLAN.md`

> **Revision note (r2):** Per review, Evoriq has **no API layer and no Sanctum**,
> and notifications are **not permission-gated** — the system just notifies.
> The originally requested `app/Http/Controllers/Api/V1/Notification/NotificationController.php`
> is therefore dropped in favour of a normal web controller:
> `app/Http/Controllers/Notification/NotificationController.php`. Real-time
> stays (SSE) because that is the only thing a JSON-ish endpoint is needed for;
> it is a plain authenticated `web` route, not an "API". All other data flows
> through Inertia props + Wayfinder as usual.
>
> **Revision note (r3):** Per-user preferences (in-app mute, sound, browser
> desktop, muted types) are **in scope now** (§11.3), as are user-initiated
> dismiss (§8) and a dev-only `notifications:send` command (§10). Retention
> default confirmed at 90 days.
>
> **Revision note (r4):** Delivery ladder made explicit (§9.0): **SSE is always
> primary, with or without Redis**. Without Redis the SSE connection runs the
> server-side DB catch-up pump (tier 2). Client-side DB polling (`Inertia
> `usePoll`) is the last-resort tier 3, used only when SSE/`EventSource` is
> genuinely unavailable — a Redis outage never downgrades to it.

---

## 1. Objective

Give Evoriq a **simple, robust in-app notification system** that:

1. Stores notifications in **three tables** — `notifications`,
   `notification_targets`, `notification_reads` — with no tenancy and no
   delivery-tracking table (single-tenant is much simpler than frc).
2. Supports **targeted** delivery: everyone, specific users, or specific roles.
3. Delivers them to the UI in **real time over SSE**, decoupled from Redis:
   Redis accelerates delivery when reachable, and a plain database poll keeps
   everything working when it is not.
4. Shows a **modern header bell** (quick popover) and a **full feed page**
   (filters, pagination, mark-as-read / mark-all-read).
5. **Prunes** old notifications from a **global setting**
   (`NOTIFICATION_RETENTION_DAYS`) on a schedule.
6. Respects **per-user preferences** (in-app mute, sound, browser desktop
   notifications, muted types) stored via `ahs12/laravel-setanjo`.
7. Follows Evoriq's Service–Repository architecture and 1:1 test mapping.

### Non-goals (this plan)

- **No API layer / no JSON REST.** Notifications are read via Inertia props and
  written via Inertia `router.post`; the only machine-facing route is the SSE
  stream (an HTML streaming GET, not a REST resource).
- **No permissions or admin CRUD for notifications.** Anyone authenticated sees
  their own feed. Notifications are created by the application (services/jobs),
  not by end users.
- Email / SMS / push channels (existing `UserInvitation` / password-reset mail
  stays as-is). An optional mail channel can layer on later.
- Quiet hours / digest scheduling (per-user *toggles* **are** in scope — §11.3).
- Broadcasting via Reverb/Pusher/Echo (`config/broadcasting.php` does not exist
  and we are not adding a websocket server).
- Notification digests / grouping UI (the `group_key` column is stored and
  reserved for a later collapse feature).

---

## 2. Current state audit

| Area | State | Notes |
| --- | --- | --- |
| API layer | **None — and not wanted** | `routes/api.php` does not exist; `bootstrap/app.php` registers only `web`, `commands`, `health`. |
| Broadcasting | **None** | No `config/broadcasting.php`, no `app/Events`, no Echo/Reverb/Pusher. |
| Redis | **Optional** | `ext-redis` present locally; app degrades to `file`/`database` drivers. Only used for a reachability probe in `DriverConfigurator`. |
| Notifications | Mail only | `app/Notifications/{UserInvitation,ResetPasswordNotification}.php` (`ShouldQueue`, `via(['mail'])`). No DB/in-app channel, no `notifications` table. |
| Settings | Setanjo | `app/Enums/SettingKey.php` + `SettingService`, `settings:sync`, `SettingSeeder`. |
| Queue | Registry | `App\Registry\QueueRegistry` + `QueueName` (`critical`/`default`/`heavy`). Jobs dispatched after `DB::transaction` (no `afterCommit()` convention). |
| Frontend | Inertia-first | Header top-right is `components/app-sidebar-header.tsx` (`ml-auto`), where `ThemeToggle` sits. `Popover`, `Badge`, `Dialog`, `sonner` all available. **No** `EventSource`, `axios`, `date-fns`, `react-query`. Strict rule: no ad-hoc fetch. |

---

## 3. Locked decisions (from review)

| # | Question | Decision |
| --- | --- | --- |
| 1 | Tables | **Three**: `notifications`, `notification_targets`, `notification_reads`. No `notification_deliveries` — unread state derives from the **absence** of a read row. |
| 2 | Fan-out | **Read-time addressability** (frc's `resolveUserTargetForQueryBuilder`) instead of writing a delivery row per user. Targets → users resolved when querying the feed/count. |
| 3 | Real-time | **Inertia `usePoll` on the shared prop** (15s, paused while the tab is hidden) — no long-lived connections, so it cannot pin PHP workers. New ids detected client-side trigger toast/sound/desktop. |
| 4 | Redis role | **Not used for delivery.** (SSE + Redis remains the deferred design, see §9.) |
| 5 | Feed page | **Inertia** (server-driven props, pagination via query string) — consistent with `users/index`. |
| 6 | Header bell | Rendered from a **shared Inertia prop** `notifications` (`unread_count` + latest ~8); live-refreshed by SSE. No ad-hoc fetch for the bell. |
| 7 | API | **None.** No `routes/api.php`, no `Api\V1` namespace, no Sanctum, no JSON CRUD. |
| 8 | Authz | **No permissions / no policy.** Any authenticated user reads/marks their own feed; access is enforced by addressability (you only see notifications targeted to you). |
| 9 | Controller home | `app/Http/Controllers/Notification/NotificationController.php` (Inertia + read actions) and `…/NotificationStreamController.php` (SSE). |
| 10 | Priorities | `info`, `success`, `warning`, `critical` (frc had info/warning/critical; add `success` for UI richness). |
| 11 | Targets | `all`, `user`, `role` (frc's BRANCH/TENANT/GLOBAL collapse to `all`). |
| 12 | Pruning | Global setting `NOTIFICATION_RETENTION_DAYS` (default 90) + scheduled `notifications:prune`. |
| 13 | Enablement | Global setting `NOTIFICATION_ENABLED` (default `true`) acts as a kill switch for creation. |
| 14 | Creation | **Internal only** via `NotificationService::create(...)`. Dev-only `notifications:send` command for manual testing (not exposed over HTTP). |
| 15 | User preferences | **In scope.** Per-user `inapp` / `sound` / `desktop` toggles + `muted_types`, stored via Setanjo and applied client-side in the stream hook. |
| 16 | Dismiss | **In scope.** Users can dismiss (delete) a notification addressed to them. |

---

## 4. Architecture overview

```text
Producer (service/job/internal command)
   │  app(NotificationService::class)->create(NotificationDTO)
   ▼
NotificationService ──transaction──> NotificationRepository ──> PostgreSQL
   │  1. insert notification
   │  2. insert targets
   │  3. after commit: FanOutNotificationJob::dispatch(id)
   │        └─ NotificationStreamNotifier::publish(id)
   │             ├─ Redis reachable?  → push id to per-recipient list
   │             └─ no Redis          → no-op (stream polls DB)
   ▼
SSE GET /notifications/stream   (auth web route, text/event-stream)
   NotificationStreamController
   └─ NotificationStreamPump (driver: auto|database|redis)
        ├─ RedisStreamPump   : BLPOP notif:u:{id} (5s) then DB catch-up
        └─ DatabaseStreamPump: sleep(interval) then DB catch-up
             both emit event: notification / unread / ping
   ▼
Browser EventSource (useNotificationStream hook)
   ├─ router.reload({ only: ['notifications'] })   ← bell + page refresh
   └─ toast / sound / desktop per user preferences

Inertia shared prop `notifications`
   └─ HandleInertiaRequests (unread_count + latest 8)
        ├─ NotificationBell (Popover)      ← header top-right
        └─ pages/notifications/index       ← full feed page (Inertia)
```

Layering strictly follows `app/Http/Controllers → Service → Repository → Model`.
The only routes are Inertia pages/actions and the SSE stream.

---

## 5. Data model (three migrations)

> Conventions: `#[Fillable]` attributes, `casts()` method, audit `created_by`
> with `nullOnDelete`, ISO timestamps, indexes on filterable/foreign columns,
> `cascadeOnDelete` for owned child rows.

### 5.1 `notifications`

`database/migrations/xxxx_create_notifications_table.php`

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->string('type');                                  // NotificationType value
    $table->string('priority')->default(NotificationPriority::INFO->value);
    $table->string('title');
    $table->text('body')->nullable();
    $table->string('action_url')->nullable();                // deep link target
    $table->json('data')->nullable();                        // arbitrary payload (icons, ids)
    $table->string('group_key')->nullable();                 // reserved for collapsing
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('expires_at')->nullable();             // per-notification TTL override
    $table->timestamps();

    $table->index('created_at');
    $table->index(['type', 'created_at']);
    $table->index('priority');
    $table->index('expires_at');
    $table->index('group_key');
});
```

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint PK | no | auto | |
| type | varchar | no | — | `NotificationType` |
| priority | varchar | no | `info` | `NotificationPriority` |
| title | varchar(255) | no | — | |
| body | text | yes | null | |
| action_url | varchar | yes | null | relative/in-app URL for the CTA |
| data | json | yes | null | cast `array` |
| group_key | varchar | yes | null | reserved |
| created_by | bigint FK→users | yes | null | `nullOnDelete` |
| expires_at | timestamp | yes | null | |
| created_at / updated_at | timestamp | yes | null | |

### 5.2 `notification_targets`

`database/migrations/xxxx_create_notification_targets_table.php`

```php
Schema::create('notification_targets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
    $table->string('target_type');                                   // all | user | role
    $table->string('target_id')->nullable();                         // user id / role id; null for all
    $table->timestamps();

    $table->index('notification_id');
    $table->index(['target_type', 'target_id']);
});
```

`target_id` is a **string** (holds user or role id; `null` for `all`), cast to
`integer` in the model when present — mirrors frc.

### 5.3 `notification_reads`

`database/migrations/xxxx_create_notification_reads_table.php`

```php
Schema::create('notification_reads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->timestamp('read_at');
    $table->timestamp('dismissed_at')->nullable();   // per-user dismiss
    $table->timestamps();

    $table->unique(['notification_id', 'user_id']);   // idempotent mark-as-read
    $table->index(['user_id', 'read_at']);
    $table->index(['user_id', 'dismissed_at']);
});
```

A row exists **only when read**; absence = unread. The unique constraint makes
`firstOrCreate` idempotent. Dismissal reuses the same row (it also sets
`read_at` when absent) and hides the notification from the user's feed without
affecting other recipients.

**Why no `notification_deliveries`:** addressability is computed at query time
(`USER` matches user id, `ROLE` matches any of the user's role ids, `ALL`
matches everyone). That removes an eagerly-fanned table and a whole class of
consistency bugs, at the cost of a slightly heavier feed query — acceptable at
Evoriq's scale and indexed well.

---

## 6. Enums

`app/Enums/NotificationPriority.php`
```php
enum NotificationPriority: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public function label(): string { /* match */ }
}
```

`app/Enums/NotificationTargetType.php`
```php
enum NotificationTargetType: string
{
    case ALL = 'all';
    case USER = 'user';
    case ROLE = 'role';

    public function label(): string { /* match */ }
}
```

`app/Enums/NotificationType.php` — typed event set (extensible; the `type`
column stays a string so new cases need no migration):
```php
enum NotificationType: string
{
    case SYSTEM_ANNOUNCEMENT = 'system.announcement';
    case USER_INVITED = 'user.invited';
    case EXPORT_COMPLETED = 'export.completed';
    case EXPORT_FAILED = 'export.failed';
    case CLOCKIFY_SYNC_COMPLETED = 'clockify.sync.completed';
    case CLOCKIFY_SYNC_FAILED = 'clockify.sync.failed';

    public function label(): string { /* match */ }
    public function defaultPriority(): NotificationPriority { /* match */ }
    public function icon(): string { /* lucide icon name, consumed by the UI */ }
}
```

---

## 7. Backend module map

```text
app/
├─ Enums/
│   ├─ NotificationPriority.php
│   ├─ NotificationTargetType.php
│   └─ NotificationType.php
├─ Models/
│   ├─ Notification.php
│   ├─ NotificationTarget.php
│   └─ NotificationRead.php
├─ DTOs/Notification/
│   ├─ NotificationDTO.php            // create payload
│   ├─ NotificationTargetDTO.php      // one target
│   └─ NotificationFeedFilterDTO.php  // feed filters (unread_only, priority, limit)
├─ DTOs/Setting/
│   └─ NotificationPreferenceDTO.php  // per-user preferences payload
├─ Repositories/Contracts/
│   └─ NotificationRepositoryInterface.php
├─ Repositories/Notification/
│   └─ NotificationRepository.php
├─ Services/Notification/
│   ├─ NotificationService.php
│   ├─ NotificationStreamNotifier.php     // publish to Redis when available
│   └─ Stream/
│       ├─ NotificationStreamPump.php      // interface
│       ├─ DatabaseStreamPump.php
│       └─ RedisStreamPump.php
├─ Services/Setting/
│   └─ NotificationPreferenceService.php  // per-user prefs (mirrors AppearanceService)
├─ Jobs/Notification/
│   └─ FanOutNotificationJob.php           // resolves recipients → notifier (queue: default/critical)
├─ Http/Controllers/Notification/
│   ├─ NotificationController.php          // Inertia feed page + mark read actions
│   └─ NotificationStreamController.php    // SSE
├─ Http/Controllers/Setting/
│   └─ NotificationPreferenceController.php // per-user preference edit/update
├─ Http/Resources/Notification/
│   ├─ NotificationResource.php
│   └─ NotificationTargetResource.php
├─ Registry/
│   └─ RedisAvailability.php               // reachability + driver resolution (reuses DriverConfigurator)
└─ Console/Commands/Notification/
    ├─ PruneNotificationsCommand.php
    └─ SendTestNotificationCommand.php      // dev-only manual notify

config/notification.php                    // stream timings, driver, limits
database/migrations/*_create_notifications_table.php
database/migrations/*_create_notification_targets_table.php
database/migrations/*_create_notification_reads_table.php
database/factories/NotificationFactory.php
database/factories/NotificationTargetFactory.php
database/factories/NotificationReadFactory.php

routes/notifications.php                   // required from routes/web.php
```

Bindings added to `app/Providers/RepositoryServiceProvider.php`
(`NotificationRepositoryInterface` → `NotificationRepository`).

### 7.1 Repository contract (sketch)

```php
interface NotificationRepositoryInterface
{
    public function feedFor(int $userId, NotificationFeedFilterDTO $filters): LengthAwarePaginator;
    public function unreadCountFor(int $userId): int;
    public function recentFor(int $userId, int $limit): Collection;
    public function getAfterId(int $userId, int $afterId, int $limit): Collection;   // SSE catch-up
    public function findById(int|string $id, array $relations = []): ?Notification;
    public function create(array $data): Notification;
    public function createTargets(Notification $notification, array $targets): void;
    public function deleteTargets(Notification $notification): void;
    public function delete(Notification $notification): bool;
    public function markRead(int $notificationId, int $userId): bool;                // true when newly created
    public function markAllRead(int $userId, CarbonInterface $timestamp): int;
    public function isAddressable(int $notificationId, int $userId): bool;
    public function resolveTargetUserIds(Notification $notification): Collection;
    public function pruneExpired(int $retentionDays, int $chunk = 500): int;
}
```

**Addressability rule** (single place, grouped `orWhere` inside a closure per
`AGENTS.md` §7.11):

```php
$roleIds = $user->roles()->pluck('id');

$query->whereHas('targets', function ($q) use ($userId, $roleIds) {
    $q->where(function ($q) use ($userId, $roleIds) {
        $q->where('target_type', NotificationTargetType::ALL->value)
          ->orWhere(fn ($q) => $q->where('target_type', NotificationTargetType::USER->value)
                                  ->where('target_id', (string) $userId))
          ->orWhere(fn ($q) => $q->where('target_type', NotificationTargetType::ROLE->value)
                                  ->whereIn('target_id', $roleIds->map(fn ($id) => (string) $id)));
    });
});
```

### 7.2 Service (sketch)

- `create(NotificationDTO $dto, ?int $createdBy = null): Notification`
  - short-circuit (return a no-op) when `NOTIFICATION_ENABLED` is false;
  - normalize targets (empty ⇒ `ALL`), `DB::transaction` → `repository->create`
    + `createTargets`;
  - **after** the transaction: `FanOutNotificationJob::dispatch($notification->id)`.
- `feedFor` / `unreadCountFor` / `recentFor` / `getAfterId` — delegate.
- `markRead(Notification $n, int $userId): void` — guard `isAddressable`, then
  `repository->markRead` (ignore already-read).
- `markAllRead(int $userId): void` — `repository->markAllRead`.
- `pruneExpired(int $days): int` — delegate.
- `publishStream(Notification $n): void` — resolve recipients and hand to
  `NotificationStreamNotifier` (no-op without Redis).

Job is thin: resolve `NotificationService` → `publishStream`. Queue channel from
`QueueRegistry` (`critical` for `CRITICAL` priority, else `default`).

---

## 8. Routes (web only)

`routes/notifications.php` (required from `routes/web.php`, alongside the other
module route files):

```php
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationStreamController;

Route::middleware(['auth', 'verified'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');

        Route::get('stream', NotificationStreamController::class)->name('stream');
    });
```

| Verb | URI | Action | Returns |
| --- | --- | --- | --- |
| GET | `/notifications` | `NotificationController@index` | Inertia `notifications/index` (paginated `feed` prop) |
| POST | `/notifications/{notification}/read` | `NotificationController@markRead` | back / 204 (`router.post`) |
| POST | `/notifications/read-all` | `NotificationController@markAllRead` | back / 204 |
| DELETE | `/notifications/{notification}` | `NotificationController@destroy` | back / 204 (dismiss own) |
| GET | `/notifications/stream` | `NotificationStreamController` (invokable) | `text/event-stream` |

- `markRead`/`destroy` verify `isAddressable($id, auth()->id())` and `abort(404)`
  otherwise — the user-model is the only access control needed.
- **Prop naming:** the shared prop is `notifications` (bell summary); the feed
  page's paginated list is `feed`, so the two never collide. The SSE hook
  reloads `only: ['notifications', 'feed']`.
- CSRF is handled by the `web` group automatically; Inertia `router.post` sends
  the token. `EventSource` is a GET and needs no token.
- No `throttle` on `stream` (a reconnect costs ~1 request/minute); the other
  routes inherit no explicit throttle, matching the rest of the app.

---

## 9. SSE design (Redis-optional)

### 9.0 Delivery ladder

Real-time delivery is **always SSE first**; Redis merely makes it faster. The
fallback order is fixed and must not be inverted:

| Tier | Condition | Mechanism | Latency |
| --- | --- | --- | --- |
| 1 | SSE + Redis reachable | `RedisStreamPump` — Redis list wakes the loop, then a DB catch-up confirms | ~instant |
| 2 | SSE, **no Redis** | `DatabaseStreamPump` — the persistent SSE connection does a server-side DB catch-up check each tick | ~3s |
| 3 | SSE/`EventSource` unavailable (client) | Inertia `usePoll` on the `notifications` prop — **last resort only** | ~30–60s |

> **Rule:** never fall back to client polling while SSE is possible. Redis being
> absent changes *which SSE pump* runs, not *whether* SSE is used. A Redis
> outage never downgrades a client to tier 3.

### 9.1 Connection lifecycle

`NotificationStreamController` (invokable):

1. `auth` already guarantees a user; read `auth()->id()`.
2. **Release the session lock immediately** — with `file`/`database` session
   drivers a long-lived request holds the lock and would block the rest of the
   SPA: `$request->session()->save();`. Then
   `ignore_user_abort(true); set_time_limit(0);`.
3. Send headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`,
   `X-Accel-Buffering: no`, `Connection: keep-alive`.
4. Emit `retry: 3000`, an initial `connected` event (`{ unread_count, last_id }`),
   then flush buffers (`while (ob_get_level()) ob_end_flush(); flush();`).
5. Choose a pump via `RedisAvailability` (or `NOTIFICATION_STREAM_DRIVER`):
   - **redis** → `RedisStreamPump`
   - **database** → `DatabaseStreamPump`
6. Loop until the client disconnects (`connection_aborted()`) or the deadline
   (`config('notification.stream.seconds', 30)`). On deadline, emit a final
   `close` event and return so `EventSource` reconnects within ~3s.
7. Emit a `ping` comment every `ping_seconds` (10s). The ping is what lets the
   server notice a vanished client, and `ignore_user_abort(false)` lets PHP
   abort the script then — so a worker is never pinned long after a tab closes.

### 9.2 Pumps

**DatabaseStreamPump (SSE without Redis — tier 2, always correct)**
- Still the persistent SSE connection; the tick only re-reads the DB to detect
  new rows (a *server-side catch-up check*, not the client polling fallback).
- Every `stream.poll_seconds` (default 3s, or 2s when Redis is present as a
  backstop) call `repository->getAfterId($userId, $lastId, 20)`.
- Emit one `notification` event per new row (with `id` as SSE `id:`), update
  `lastId`; emit `unread` with the fresh count.

**RedisStreamPump (acceleration)**
- `Redis::connection()->blpop(["notif:u:{$userId}"], 5)`; on a hit, run the same
  DB catch-up as above (so a missed/expired list entry can never lose a
  notification).
- Redis list is a **signal only**, never the source of truth: `LPUSH` +
  `EXPIRE` (short TTL, e.g. 300s) per recipient; DB holds the notification.
- `NotificationStreamNotifier::publish($notificationId)` resolves recipients via
  `resolveTargetUserIds()` and pipes the pushes. No Redis ⇒ the method no-ops.

This satisfies "use Redis if available, don't be bound to it": correctness is
identical with and without Redis; Redis only lowers latency/DB load.

### 9.3 Client

`resources/js/hooks/use-notification-stream.ts`:

- Create `EventSource(stream.url())` (same-origin ⇒ cookies sent automatically).
- On `notification`: debounced `router.reload({ only: ['notifications'] })`
  (and, when on the feed page, prepend to the local list). If the notification's
  type is not in `preferences.muted_types` **and** `preferences.inapp` is true,
  show a `toast` via sonner; if `preferences.sound` play a short client-side
  sound; if `preferences.desktop` and permission granted, fire a browser
  `Notification`.
- On `unread`: update the shared count optimistically.
- Handle `onerror` with backoff; `EventSource` reconnects natively, but guard
  against spam. Expose `connected` state for a UI indicator.
- Feature-detect `EventSource`. **Only if it is genuinely unavailable** (no
  streaming support / connection cannot be established) fall back to Inertia
  `usePoll` on the `notifications` prop (mirrors
  `pages/admin/settings/developer.tsx`) — the last-resort tier 3. A Redis outage
  never triggers this path; the server keeps serving SSE via tier 2.
- **Worker safety (important):** SSE holds a PHP worker for the connection's
  lifetime, so the hook opens **exactly one** connection (the header bell) and
  **closes it while the tab is hidden** (`visibilitychange`), reconnecting when
  visible. The first connection is deferred ~750ms so it never competes with the
  page load. Do **not** mount `useNotificationStream()` on individual pages —
  the bell already drives partial reloads of `feed`.

> This is a **deliberate, documented exception** to
> `frontend-architecture.md`'s "no ad-hoc fetch" rule. Every other read/write
> goes through Inertia props + Wayfinder; SSE is additive and isolated in one
> hook.

### 9.4 `config/notification.php`

```php
return [
    'stream' => [
        'driver' => env('NOTIFICATION_STREAM_DRIVER', 'auto'), // auto|database|redis
        'seconds' => (int) env('NOTIFICATION_STREAM_SECONDS', 55),
        'poll_seconds' => (int) env('NOTIFICATION_STREAM_POLL_SECONDS', 3),
        'redis_poll_seconds' => (int) env('NOTIFICATION_STREAM_REDIS_POLL_SECONDS', 2),
        'key_prefix' => 'notif:u:',
        'key_ttl' => (int) env('NOTIFICATION_STREAM_KEY_TTL', 300),
    ],
    'feed' => [
        'recent_limit' => (int) env('NOTIFICATION_RECENT_LIMIT', 8),
        'per_page' => (int) env('NOTIFICATION_PER_PAGE', 20),
    ],
];
```

`RedisAvailability` returns `true` only when `extension_loaded('redis')` (or
Predis is installed **and** `Redis::connection()->ping()` succeeds), wrapped in
`try/catch (Throwable)` — same shape as `DriverConfigurator::redis()`. `auto`
resolves to `redis` when available, else `database`.

---

## 10. Notification creation & fan-out flow

```text
service->create(NotificationDTO)
  ├─ if !setting(NOTIFICATION_ENABLED) → skip
  ├─ DB::transaction
  │    ├─ repository->create([...dto, created_by])
  │    └─ repository->createTargets(notification, dto.targets)  // default ALL
  └─ FanOutNotificationJob::dispatch(notification->id)           // after commit
         └─ NotificationService::publishStream(notification)
              ├─ RedisAvailability::available() ? notifier->publish(...) : return
              └─ recipients = repository->resolveTargetUserIds(notification)
                   ALL  → all active user ids
                   USER → [target_id]
                   ROLE → users with role (spatie: whereHas('roles'))
```

Consumers call the service:

```php
app(NotificationService::class)->create(new NotificationDTO(
    type: NotificationType::EXPORT_COMPLETED,
    title: __('Export ready'),
    body: __('Your :entity export is ready to download.', ['entity' => $entity]),
    actionUrl: $downloadUrl,
    targets: [new NotificationTargetDTO(NotificationTargetType::USER, $userId)],
));
```

Creation is **internal only** — no controller route exposes it. A dev-only
`notifications:send {--title=} {--user=} {--role=} {--all}` command can call the service so
the feature can be exercised locally without a producer feature. A small global
`notify()` helper (frc parity) is **optional**; recommend the typed service call
to stay explicit.

---

## 11. Settings & pruning

### 11.1 Global settings (`app/Enums/SettingKey.php`)

Add a new `notifications` group with:

| Key | Type | Default | Rules |
| --- | --- | --- | --- |
| `NOTIFICATION_ENABLED` | boolean | `true` | `boolean` |
| `NOTIFICATION_RETENTION_DAYS` | integer | `90` | `integer\|min:1\|max:3650` |

Add match arms to `group()`, `label()`, `description()`, `defaultValue()`,
`type()`, `rules()`, and `'notifications' => 'Notifications'` to `groups()`.
Run `php artisan settings:sync`; `SettingSeeder` covers tests.

### 11.2 Prune command

`app/Console/Commands/Notification/PruneNotificationsCommand.php`
(`notifications:prune`):

- Reads `NOTIFICATION_RETENTION_DAYS`.
- Calls `NotificationService::pruneExpired($days)`.
- Deletes notifications where `expires_at < now()` **or**
  `created_at < now()->subDays($days)`; child `targets`/`reads` go via
  `cascadeOnDelete` (bulk `delete()` in chunks of 500 to avoid memory spikes).
- Supports `--days=` override and `--dry-run`.

Schedule in `routes/console.php`:

```php
Schedule::command('notifications:prune')->daily();
```

### 11.3 Per-user preferences (in scope)

Small per-user toggles stored with Setanjo via `$user->settings()`, mirroring
`AppearanceService` exactly.

`app/Enums/UserSettingKey.php` — add:

| Case | Value | Meaning |
| --- | --- | --- |
| `NOTIFICATION_INAPP_ENABLED` | `notifications.inapp` | Master in-app toggle. When `false`, suppress live toasts (bell + feed still work). |
| `NOTIFICATION_SOUND_ENABLED` | `notifications.sound` | Play a subtle sound on arrival. |
| `NOTIFICATION_DESKTOP_ENABLED` | `notifications.desktop` | Request browser Notification permission and show OS notifications. |
| `NOTIFICATION_MUTED_TYPES` | `notifications.muted_types` | JSON array of `NotificationType` values to suppress from toast/sound/desktop. |

`app/Services/Setting/NotificationPreferenceService.php` (mirrors
`AppearanceService`):

```php
final class NotificationPreferenceService
{
    /** @return array{inapp: bool, sound: bool, desktop: bool, muted_types: array<int,string>} */
    public function forUser(?User $user): array { /* array_merge(defaults, stored) */ }

    /** @return array<string, mixed> */
    public function stored(?User $user): array { /* $user->settings()->get(...) + friendlier casts */ }

    /** @return array{inapp: bool, sound: bool, desktop: bool, muted_types: array<int,string>} */
    public function defaults(): array
    {
        return ['inapp' => true, 'sound' => true, 'desktop' => false, 'muted_types' => []];
    }

    public function update(User $user, NotificationPreferenceDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto): void {
            $settings = $user->settings();
            $settings->set(UserSettingKey::NOTIFICATION_INAPP_ENABLED->value, $dto->inapp);
            $settings->set(UserSettingKey::NOTIFICATION_SOUND_ENABLED->value, $dto->sound);
            $settings->set(UserSettingKey::NOTIFICATION_DESKTOP_ENABLED->value, $dto->desktop);
            $settings->set(UserSettingKey::NOTIFICATION_MUTED_TYPES->value, json_encode(array_values($dto->mutedTypes)));
        });
    }
}
```

`muted_types` is stored as a JSON string and decoded defensively (`json_decode(...)
?? []`, filtered to known `NotificationType` cases) so a corrupt value never
breaks the UI. `NotificationPreferenceDTO` is `final readonly` with
`fromRequest(FormRequest): self`.

**Routes** (append to `routes/settings.php`, inside the `['auth']` group):

```php
Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.update');
```

- `edit` renders Inertia `settings/notifications` with the resolved preferences
  and the list of mappable `NotificationType` labels.
- `update` validates via a `FormRequest`, calls the service, flashes a toast, and
  redirects back.

**How preferences affect delivery**

- Preferences are **client-side only** — the stream still delivers and the bell
  count stays accurate; the hook decides whether to toast / play sound / fire a
  desktop notification, and drops `muted_types`.
- Shared with the client via the `notifications` prop (`preferences` key, §13.2)
  so no extra fetch is needed.
- Global `NOTIFICATION_ENABLED=false` (kill switch) still wins over everything.

**Frontend**

- `resources/js/pages/settings/notifications.tsx` — uses the settings layout;
  three `Switch` rows + a multi-select of types (reuse `components/app/combobox.tsx`
  or a checkbox list). Submits via `<Form {...update.form()}>` / `useForm`.
- `resources/js/components/notification/notification-preference-form.tsx` —
  presentational form component.
- Add a **Notifications** item to `sidebarNavItems` in
  `resources/js/layouts/settings/layout.tsx`.
- `resources/js/types/notification.ts` exports `NotificationPreferences`.

---

## 12. Access model (no RBAC)

- Every authenticated (`auth`, `verified`) user can:
  - view their own paginated feed (`GET /notifications`),
  - read the shared `notifications` prop (bell),
  - mark one / all of their notifications as read,
  - stream their own notifications (`GET /notifications/stream`),
  - dismiss a notification addressed to them.
- **No policy, no permission, no admin CRUD.** Authorization is implicit:
  queries and mutations are always scoped to `auth()->id()` through the
  addressability rule, so a user can never see or touch a notification that was
  not targeted to them.
- `markRead`/`destroy` on a non-addressable id return `404`.

---

## 13. Frontend design

### 13.1 Files

```text
resources/js/
├─ types/notification.ts                 + re-export in types/index.ts
├─ hooks/
│   ├─ use-notification-stream.ts        EventSource lifecycle
│   └─ use-notifications.ts              read the shared `notifications` prop
├─ lib/notification.ts                   icon/color/label maps for types+priorities
├─ components/notification/
│   ├─ notification-bell.tsx             Popover trigger + unread badge
│   ├─ notification-list.tsx             shared list renderer
│   ├─ notification-item.tsx             single row (icon, title, time, unread dot)
│   ├─ notification-empty.tsx            empty state (or reuse EmptyState)
│   └─ notification-preference-form.tsx  per-user preference form
└─ pages/
    ├─ notifications/index.tsx           full feed page
    └─ settings/notifications.tsx        per-user preference settings page
```

Edits:
- `resources/js/components/app-sidebar-header.tsx` — insert
  `<NotificationBell />` inside `<div className="ml-auto flex items-center gap-1">`
  before `<ThemeToggle />`.
- `resources/js/components/app-sidebar.tsx` — optional “Notifications” nav item.
- `resources/js/layouts/settings/layout.tsx` — add a “Notifications” nav item.
- `app/Http/Middleware/HandleInertiaRequests.php` — share `notifications`.
- Global prop type in `resources/js/types/global.d.ts` (`sharedPageProps`).

### 13.2 Shared prop

```php
'notifications' => fn () => $user ? [
    'unread_count' => $this->notifications->unreadCountFor($user->id),
    'recent' => NotificationResource::collection(
        $this->notifications->recentFor($user->id, config('notification.feed.recent_limit')),
    )->resolve(),
    'preferences' => $this->preferences->forUser($user),
] : ['unread_count' => 0, 'recent' => [], 'preferences' => $this->preferences->defaults()],
```

Cheap (indexed `LIMIT 8` + `COUNT`) and available on every page, so the bell
needs **no** ad-hoc fetch.

### 13.3 Bell (quick view)

- `Popover` with a ghost `Button` (`variant="ghost" size="icon" className="size-9"`,
  `aria-label="Notifications"`) showing a `Bell` icon and, when
  `unread_count > 0`, a `Badge` (count, `99+` cap) positioned at the corner.
- `PopoverContent align="end" className="w-96 p-0"`:
  - header: “Notifications” + “Mark all read” (`router.post(readAll.url())`).
  - scrollable list (`max-h-[70vh] overflow-y-auto`) of up to 8 recent items
    (`NotificationList`), each row: type icon (color by priority), title,
    truncated body, `created_at_diff`, unread dot, optional CTA link.
  - footer: “View all” → `Link href={notifications.index()}`.
- Clicking an item marks it read (`router.post(read.url(id), { preserveScroll: true })`)
  and follows `action_url` when present.
- Empty state: `EmptyState` / “You're all caught up”.

### 13.4 Full feed page

`pages/notifications/index.tsx`:

- `<Head title="Notifications" />`, `PageHeader` (title, description, actions:
  “Mark all read”), shell `flex h-full flex-1 flex-col gap-6 p-4`.
- Filters: `Tabs` (All / Unread) and optional priority `Select`; query-string
  driving via `useDataTableFilters` or a small equivalent (`router.get` with
  `only`, `replace`, `preserveState`).
- Paginated list (`Paginated<NotificationItem>`) with date grouping
  (Today / Earlier) optional.
- Row actions: mark read/unread, dismiss, follow `action_url`.
- `NotificationsIndex.layout = { breadcrumbs: [{ title: 'Notifications', href: index() }] }`.
- Live-updates via `useNotificationStream()` with `only: ['notifications']`.

### 13.5 Styling & conventions

- `cn()`, `cva`-provided `Badge` variants, semantic tokens
  (`text-muted-foreground`, `bg-muted`, `border`), always `dark:` variants.
- Relative time from the backend (`created_at_diff` via `diffForHumans()`),
  matching `Passkey` — no client date library.
- Icons from `lucide-react`; a `lib/notification.ts` map from
  `NotificationType`/`NotificationPriority` → icon + accent class.
- Named exports for components, default export for the page.
- Toasts via `sonner` (`toast` from `sonner`); server flashes via `flash.toast`.

---

## 14. Testing (Pest, 1:1 mapping)

| File | Type | Covers |
| --- | --- | --- |
| `tests/Unit/NotificationServiceUnitTest.php` | Unit (Mockery) | `create` normalizes empty targets to `ALL`, persists inside a transaction, dispatches the fan-out job, and skips when `NOTIFICATION_ENABLED=false`; `markRead` guards addressability and is idempotent; `markAllRead`; `pruneExpired` delegates with the setting value. |
| `tests/Unit/NotificationRepositoryUnitTest.php` | Unit (RefreshDatabase) | Addressability query matches `ALL`/`USER`/`ROLE` and excludes non-targeted/expired notifications; unread count; `markRead` `firstOrCreate` idempotency; `getAfterId` ordering. |
| `tests/Feature/NotificationFeatureTest.php` | Feature | Feed page renders with props; shared `notifications` prop present; mark-read flips `is_read` and 404s for non-addressable ids; mark-all-read; dismiss own; SSE route is not the concern here. |
| `tests/Feature/NotificationStreamFeatureTest.php` | Feature | `GET /notifications/stream` returns `text/event-stream`, emits the initial `connected` event, and terminates at the configured deadline (set `stream.seconds=1` in the test). |
| `tests/Unit/NotificationPreferenceServiceUnitTest.php` | Unit (Mockery) | Defaults when nothing stored; `stored` decodes `muted_types` defensively; `update` persists all four keys inside a transaction. |
| `tests/Feature/NotificationPreferenceFeatureTest.php` | Feature | Settings page renders; `PATCH settings/notifications` validates + persists; unauthenticated users are redirected. |
| `tests/Mock/NotificationMockData.php` | Fixture | Reusable DTO/array payloads. |

Factories: `NotificationFactory` (+ `forUser`/`forRole`/`all` target states),
`NotificationTargetFactory`, `NotificationReadFactory`.

---

## 15. Phased execution plan

**Phase 0 — Foundations**
1. Add `config/notification.php`.
2. Add the three enums.
3. Add `RedisAvailability`.

**Phase 1 — Data & repository**
4. Three migrations; three models with casts/relations.
5. `NotificationRepositoryInterface` + `NotificationRepository`
   (addressability, feed, unread, `getAfterId`, markRead/markAllRead, prune,
   target resolve).
6. Bind in `RepositoryServiceProvider`. Factories.

**Phase 2 — Service & async**
7. DTOs (`NotificationDTO`, `NotificationTargetDTO`, `NotificationFeedFilterDTO`).
8. `NotificationService` (transactions, target normalization, prune, kill switch).
9. `FanOutNotificationJob` + `NotificationStreamNotifier` + stream pumps.

**Phase 3 — HTTP**
10. `routes/notifications.php` required from `routes/web.php`.
11. `Notification/NotificationController` (index, markRead, markAllRead, destroy).
12. `Notification/NotificationStreamController` (SSE) + resources.
13. `HandleInertiaRequests` shares `notifications`.

**Phase 4 — Settings, prefs & pruning**
14. Add `SettingKey` cases + group; `settings:sync`.
15. Add `UserSettingKey` cases + `NotificationPreferenceDTO` +
    `NotificationPreferenceService`.
16. `NotificationPreferenceController` + `routes/settings.php` entries.
17. `PruneNotificationsCommand` + `Schedule::command('notifications:prune')->daily()`.
18. Dev `notifications:send` command.

**Phase 5 — Frontend**
19. `types/notification.ts` (+ global prop augmentation).
20. Feature components (`notification-bell`, `-list`, `-item`), `lib/notification.ts`.
21. `use-notification-stream` hook (preferences-aware).
22. `pages/notifications/index` + wire the bell into `app-sidebar-header.tsx`.
23. `pages/settings/notifications` + preference form + settings layout nav item.

**Phase 6 — Verify**
24. Write unit + feature tests (table above).
25. `npm run build` (regenerate Wayfinder for the new routes).
26. `composer check` — must pass (Pint, PHPStan/Larastan, Pest, lint, types).

---

## 16. Open questions

1. **Redis push cap.** For `ALL` broadcasts, cap the Redis fan-out at N
   recipients (e.g. 500) and rely on the DB poll beyond that, or push to every
   user? *Recommend: cap, since the DB poll is always correct anyway.*

*(Resolved: no API layer; no permissions/policy; Inertia + SSE only;
priorities `info`/`success`/`warning`/`critical`; creation is internal-only;
retention default 90 days; users may dismiss their own notifications; a
dev-only `notifications:send` command ships; per-user preferences are in
scope.)*

---

## 17. Risks & mitigations

| Risk | Mitigation |
| --- | --- |
| Long SSE connections exhaust PHP-FPM workers | One connection per **visible** tab, paused on `visibilitychange`; ~30s bounded connections + auto-reconnect; 10s pings so disconnects are detected fast; DB poll is the fallback. A dedicated process/pool is still advised for high traffic. |
| Session lock blocks the SPA during streaming | `$request->session()->save()` before the loop. |
| Redis outage breaks live updates | Redis is optional and auto-detected; behavior is identical without it. |
| Unread count gets expensive at scale | Heavily indexed queries; optional Redis-cached counter (behind `Cache` with array/db fallback) as a later optimization. |
| Feed query cost from read-time addressability | Indexed `notification_targets(type, target_id)` + `notifications(created_at)`; consider a materialized unread set only if metrics demand it. |
| Proxies buffering SSE | `X-Accel-Buffering: no`, `Cache-Control: no-cache`, periodic `ping` events. |
| SSE contradicting frontend "no ad-hoc fetch" rule | Isolated in one documented hook; all other data stays Inertia/Wayfinder. |
| Shared `notifications` prop adds a query per request | `LIMIT 8` + indexed `COUNT`; can be cached via `Cache` later. |
