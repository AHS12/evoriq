---
name: clockify-sync-feature
description: 'Add Clockify synchronization work safely: new synced entities, sync jobs, checkpoints, rate-limited fetching, idempotent upserts and scheduling, following TDR sections 8–24.'
argument-hint: 'Entity to sync or sync behavior, for example: sync clockify time entries for a date range.'
---

# Clockify Sync Feature

## When to use

Use when adding or changing anything that pulls Clockify data, schedules a sync,
handles a webhook, or reconciles historical data. Read `TDR.md` §8–24 and
`AGENTS.md` §7.9–7.10 first.

## Non-negotiable rules

1. **All Clockify HTTP goes through `App\Services\Clockify\ClockifyClient`.**
   Never call `Http::` against Clockify from a controller, job or module service.
2. **Never bypass the rate limiter.** Manual, scheduled and webhook syncs share
   one budget (`ClockifyRateLimiter`).
3. **Pagination is independent of the plan.** Use `ClockifyClient::paginate()`;
   never assume one range = one request.
4. **Idempotency.** Every synced table has a unique `clockify_id` (+ `workspace_id`)
   and is written with **upsert** semantics. Re-runs must not duplicate rows.
5. **Resumable.** Long imports are checkpointed in `sync_runs` / `sync_jobs`
   (`page`, `range_start`, `range_end`, `status`) so a failure resumes, not restarts.
6. **Jobs are thin and idempotent.** Resolve a service and delegate; no logic.
7. **Credentials never reach the frontend** and are stored encrypted per connection.

## 1. Add a synced entity

1. **Migration**: internal `id` + `clockify_id` (unique, indexed) + `workspace_id`
   (indexed) + foreign/internal IDs + audit columns. Add a unique composite index
   where needed (e.g. `['workspace_id', 'clockify_id']`).
2. **Model** `app/Models/Clockify{Entity}.php`: casts for dates/booleans/decimals,
   typed relations, scopes.
3. **Repository**: an `upsertByClockifyId()` (or `upsertMany()`) using
   `updateOrCreate(['workspace_id' => …, 'clockify_id' => …], $attributes)`.
   All queries live here.
4. **Service** `app/Services/Sync/{Entity}SyncService.php`: orchestrates fetch →
   map → upsert inside `DB::transaction`; increments `records_created` /
   `records_updated` counters.

```php
foreach ($this->client->forCredentials($key)->paginate(
    "/workspaces/{$workspaceId}/time-entries",
    ['start' => $from, 'end' => $to],
    $connectionKey,
) as $page => $entries) {
    DB::transaction(fn () => $this->entries->upsertMany($workspaceId, $entries));
    $this->checkpoints->advance($job, $page, count($entries));
}
```

## 2. Add a sync job

- `app/Jobs/Sync/Sync{Entity}Job.php` — thin; resolves the service and delegates.
- Make it **resumable**: read the checkpoint from `sync_jobs`, continue from
  `page`, and persist progress after each page.
- Assign a queue by priority (TDR §17): `high` (manual/webhook), `default`
  (daily), `low` (reconciliation). Priority affects ordering only — never the
  rate limiter.
- Dispatch from a service, not a controller.

## 3. Scheduling & webhooks

- Register recurring work in `routes/console.php`:
  `Schedule::job(new SyncDailyJob)->dailyAt('01:00');`
- Daily sync = today/yesterday/previous 7 days; weekly = previous 30–31 days
  (TDR §19 rolling reconciliation).
- Webhook handlers must **acknowledge quickly** and queue processing; validate
  the webhook token before doing anything.
- Isolate the experimental Entities Changes API behind an adapter so it can be
  swapped if Clockify changes it (TDR §20).

## 4. Testing

Always fake the network — never hit the real API.

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'https://api.clockify.me/api/v1/workspaces/ws-1/time-entries*' => Http::sequence()
        ->push([['id' => 'e1', 'description' => 'A']], 200, ['Last-Page' => 'false'])
        ->push([['id' => 'e2', 'description' => 'B']], 200, ['Last-Page' => 'true']),
]);

// run the job/service, then assert idempotency:
$service->sync($connection, $from, $to);
$service->sync($connection, $from, $to); // second run
expect(ClockifyTimeEntry::where('workspace_id', 'ws-1')->count())->toBe(2);
```

Assert: correct endpoint/headers sent, upsert (no duplicates on re-run), counters
updated, checkpoint advanced, and failure resumes from the last page.

## 5. Completion checks

- [ ] All HTTP via `ClockifyClient`; rate limiter respected.
- [ ] Unique `clockify_id`; upsert semantics proven idempotent by a test.
- [ ] Job is thin, queued with the right priority, and resumable via checkpoints.
- [ ] No credentials logged or sent to the frontend.
- [ ] `Http::fake()` tests cover success, pagination, and re-run idempotency.
- [ ] `composer check` passes.
