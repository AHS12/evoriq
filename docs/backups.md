# Backups

Evoriq ships with an automated backup system built on
[spatie/laravel-backup](https://spatie.be/docs/laravel-backup). It archives all
application files plus a database dump into a single zip, stores it on the
configured destination, prunes old archives and continuously checks the health
of your backups.

## Where it lives

- **Administration → Settings → Backups** — schedule, destination, retention
  and the run history (`backup.view` / `backup.manage` permissions).
- **Administration → Settings → Remote storage** — S3-compatible credentials
  for an off-site destination (`settings.view` / `settings.update`).

Both tabs are permission-gated; the backup settings write to the database, the
remote storage credentials are written to the **`.env`** file (they are
infrastructure configuration, on the same tier as `DB_*` and `REDIS_*`).

## Schedule

The schedule is **database-driven**: the scheduler runs a lightweight
`backup:schedule-tick` command every five minutes, and that command reads the
backup settings and dispatches exactly the work that is due.

- **Frequency** — daily or weekly (Sunday).
- **Time** — fixed slots that avoid the DST transition window (02:00–03:00).
- **Enabled/disabled** — disabling the schedule stops new backups; the daily
  health check keeps running so a stale backup is still reported.

Changing the settings takes effect on the **next scheduler tick** — no code
change, no scheduler restart. Manual **"Run backup now"** queues the same job
the scheduler uses, on the `heavy` queue channel.

## Destination & retention

Backups **always land on the local `backups` disk**
(`storage/app/private/backups`). When remote storage is configured, the
**"Also send backups to remote storage"** setting adds the remote disk as a
second destination — spatie writes the archive to every destination, so the
local copy always exists even if the remote copy fails.

| Setting | Meaning |
| --- | --- |
| Also send to remote | Mirror every backup to the configured S3/R2 bucket |
| Keep all backups for (days) | Every backup is kept this long; after that, cleanup keeps only daily/weekly/monthly/yearly milestones (spatie's `DefaultStrategy`) |
| Maximum storage (MB) | Oldest backups are removed once backups exceed this size |
| Freshness check (days) | A backup older than this is flagged **unhealthy** |

## Remote storage (S3 / R2)

Configure **Administration → Settings → Remote storage**:

1. Pick a provider — Amazon S3, Cloudflare R2, or any custom S3-compatible
   endpoint. The preset fills in region and path-style defaults.
2. Enter the access key, secret, region, bucket and (for R2/custom) the
   endpoint. Cloudflare R2 requires the **path-style endpoint** toggle.
3. **Test connection** writes, reads and deletes a probe file on the bucket.
4. Save. The credentials are written to `.env` (`AWS_*` keys) and queue workers
   are restarted so they pick up the new configuration.

Then enable **Backups → Also send backups to remote storage**. The local copy
always exists; the remote bucket receives a mirror of every backup. The secret
is never displayed again and is never written to the audit trail — only the
fact that remote storage settings changed is logged.

## Email delivery

With **Backups → Email backup archives** enabled, every successful backup is
emailed (queued) with the zip **attached**. Recipients, in priority order:

1. **Email recipients** in the backup settings (comma-separated addresses)
2. **`BACKUP_EMAIL_TO`** in `.env` (comma-separated) when the setting is empty
3. otherwise the users holding the `backup.manage` permission

Archives larger than `BACKUP_EMAIL_MAX_ATTACHMENT_MB` (default 10 MB) are sent
**without** the attachment — the body points to the run history download
instead, since mail servers reject oversized mail. Mail delivery problems
never affect the backup itself.

## Run history & notifications

Every backup, cleanup and health check is recorded with its status, trigger
(scheduled/manual), size, duration, output and the user who triggered it (for
manual runs). Completed backups can be downloaded from the run history.

Failures and unhealthy checks create an in-app notification for everyone with
the `backup.manage` permission (toggleable). Settings changes and backup
triggers are recorded on the audit trail.

## Restore (manual runbook)

`spatie/laravel-backup` intentionally ships without a restore feature. To
restore:

1. Download the backup zip from **Settings → Backups → Run history** (or pull
   it from the remote bucket).
2. **Database:** unzip, locate the dump (e.g. `evoriq.sql`) and import it:
   ```sh
   psql -h 127.0.0.1 -U postgres -d evoriq -f evoriq.sql
   ```
3. **Files:** copy the archived application directories back into the project,
   preserving `.env` (or restore it selectively — the zip contains it).
4. Run `php artisan config:clear` and restart the queue workers
   (`php artisan queue:restart`).

Test your restore path regularly — a backup you cannot restore is not a backup.

## Environment fallbacks

The settings UI overrides these `.env` fallbacks (see `.env.example`):
`BACKUP_KEEP_DAYS`, `BACKUP_MAX_MB`, `BACKUP_MAX_AGE_DAYS`,
`BACKUP_ARCHIVE_PASSWORD` (optional zip encryption),
`BACKUP_EMAIL_MAX_ATTACHMENT_MB`, `DB_DUMP_TIMEOUT`.

> **Windows dev note:** database dumps need `pg_dump`/`mysqldump` on the PATH
> (Laragon and Laragon-like tools ship it). A missing binary does not corrupt
> anything — the run is recorded as failed with the underlying message.
