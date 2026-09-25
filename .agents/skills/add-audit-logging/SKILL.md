---
name: add-audit-logging
description: 'Add audit trail coverage for a model/module: LogsActivity trait with a selective allowlist, named domain events via AuditLogService, redaction check and tests.'
argument-hint: 'Entity name, for example: Invoice or Report.'
---

# Add Audit Logging

## When to use

When a new (or existing) model or module must appear in the audit trail at
`/audit-logs`. The collector is `spatie/laravel-activitylog` v5; the house
conventions live in `AGENTS.md` §7.16 and `config/audit.php`.

**Core principle: audit events, not churn.** Apply the "would anyone dispute
this change?" test — a role change or a status transition is an event; a
`last_seen_at` heartbeat or an `updated_at` bump is noise. Never log with
`logAll()` / `logFillable()`.

## Steps

1. **Pick the channel** (`App\Enums\AuditLogName`): `auth`, `security`,
   `rbac`, `settings` or `domain`. Add a new case only for a genuinely new
   domain, together with its `retentionDays()` window in `config/audit.php`.

2. **Decide the events** (`App\Enums\AuditEvent`): the four model events
   (`created`/`updated`/`deleted`/`restored`) are collected automatically;
   anything more meaningful (e.g. `refund_issued`) is a named event recorded
   via `AuditLogService::record()` — add an enum case + label.

3. **Add the trait to the model** (only for models whose column changes are
   worth auditing — high-frequency models like `DataProcessingJob` get named
   events only):

   ```php
   use Spatie\Activitylog\Models\Concerns\LogsActivity;
   use Spatie\Activitylog\Support\LogOptions;

   public function getActivitylogOptions(): LogOptions
   {
       return LogOptions::defaults()
           ->useLogName(AuditLogName::DOMAIN)
           ->logOnly(['status', 'total'])   // selective allowlist — never logAll()
           ->logOnlyDirty()
           ->dontLogEmptyChanges()
           ->dontLogIfAttributesChangedOnly(['updated_by'])
           ->setDescriptionForEvent(fn (string $event): string => "Invoice {$event}");
   }
   ```

4. **Record named events in the service** (never from controllers or jobs):

   ```php
   $this->audit->record(
       AuditEvent::REFUND_ISSUED,
       $invoice,
       ['amount' => $dto->amount],
       actor: auth()->user(),
       channel: AuditLogName::DOMAIN,
   );
   ```

   - Call it **inside the service**, ideally inside the `DB::transaction`.
   - In queued jobs there is no authenticated user: capture the actor id on
     the request thread and pass it explicitly (`User::find($actorId)`).

5. **Redaction check** — confirm no sensitive column can enter the diff.
   `App\ActivityLog\AuditLogAction` redacts anything matching
   `config/audit.php` `redacted_attributes` as a backstop, and
   `config/activitylog.php` `default_except_attributes` excludes them
   pre-diff. If a new sensitive column appears, extend both lists.

6. **Subject label** (optional) — so the UI renders a readable label, add an
   arm in `AuditLogResource::subjectLabel()` (`app/Http/Resources/AuditLog/`).

7. **Test it** — follow `tests/Feature/AuditLog/AutomaticLoggingTest.php`:
   - create/update/delete → exactly one row with a dirty-only diff;
   - `updated_at`-only save → no row;
   - sensitive values → `[REDACTED]` or absent from `attribute_changes`.

## Notes

- Rows are browsable at `/audit-logs` (`audit.view` / `audit.view.all` /
  `audit.manage` permissions — see the `add-permission` skill if a new gate
  is needed).
- Every row written during one HTTP request shares a `correlation_id`
  (`App\ActivityLog\AuditContext`, booted by `AuditRequestContext`
  middleware); the UI can group related events by it.
- Retention is enforced per channel by the scheduled `audit:clean` command;
  a manual prune action exists for `audit.manage` holders.

## Completion checks

- [ ] Channel + event cases exist (with labels); retention set for new channels.
- [ ] Model trait uses `logOnly` allowlist + `logOnlyDirty` + `dontLogEmptyChanges`.
- [ ] Named events recorded from the service, never controllers/jobs.
- [ ] Redaction lists verified; no sensitive values can reach the log.
- [ ] Audit feature test added and green.
- [ ] `composer check` passes.
