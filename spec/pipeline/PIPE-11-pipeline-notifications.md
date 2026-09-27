# PIPE-11 — Pipeline lifecycle notifications

- **Status:** Draft
- **Epic:** pipeline
- **Estimate:** S
- **Depends on:** PIPE-02
- **Blocks:** —
- **TDR:** §34, §35, §38

## 1. Why

Because long work continues in the background, notifications are the safety net:
the user should learn about a finished import or a failed sync even if they left
the page. The Data Processing Center already notifies on completion/failure
in-app; this spec makes pipeline notifications consistent, deep-linked, and
controllable, and reserves the Clockify sync cases.

## 2. Scope

**In**

- Lifecycle notification types: **queued** (long jobs only), **completed**,
  **failed**, **retrying**, **cancelled** (existing).
- Deep links to `activity.show` / artifact download (existing pattern).
- A user preference category "Pipeline" to mute/allow these notifications.
- Reserve/map the existing `CLOCKIFY_SYNC_*` types onto the same pipeline
  lifecycle for when sync lands (SYNC-09).
- Emit only on meaningful transitions; never on progress.

**Out**

- Email/SMS delivery (post-MVP; in-app only for now).
- Notification UI component changes beyond type icon/link support.
- Alerting for operators (OPS-04).

## 3. Data model

- None. Uses existing `notifications`/`notification_targets`/`notification_reads`.

## 4. Backend

- **Enum `NotificationType`:** ensure cases exist and carry `label()`,
  `icon()`, `category()`:
  - `JOB_QUEUED`, `JOB_COMPLETED`, `JOB_FAILED`, `JOB_RETRYING`,
    `JOB_CANCELLED` (map/replace the ad-hoc usage if any);
  - keep `CLOCKIFY_SYNC_COMPLETED`/`CLOCKIFY_SYNC_FAILED`.
- **Service:** centralize creation in `NotificationService` (already the
  pattern). `DataProcessingJobService::notifyFinished()` and the retry path call
  it; add `notifyQueued()` for runs whose planned duration exceeds a threshold
  (long imports).
- **Dispatch timing:** after commit (never inside the transaction that writes
  the job) per `AGENTS.md` §7.9. Notifications themselves implement
  `ShouldQueue`.
- **Targeting:** the run owner (`user_id`); operators are not spammed (health
  page covers them).
- **Preferences:** add a "Pipeline" notification category to
  `NotificationPreferenceService` + admin/user preference surfaces; respect it
  before creating a notification.
- **Content:** title/body are translation keys with parameters (job name,
  entity, counts, reason). Action URL points to `activity.show` or the artifact
  download.

## 5. Frontend / UI

- `components/notification/notification-item.tsx`: render pipeline icons and
  make the action navigate to `activity.show` (or download). No new page.
- `components/notification/notification-preference-form.tsx`: add the Pipeline
  category toggle group.
- Grouping/dedupe: repeated retries notify once per run (update the existing
  notification if possible, or emit a single "retrying" and a final outcome).

### A11y & i18n

- Notification titles/bodies are translated; icons decorative.
- Preference labels translated in the five `lang/app/*.json`.

## 6. API / routes / props

- No new routes; existing `notifications.*` transport is reused.

## 7. Acceptance criteria

- [ ] Completing, failing, retrying and (for long jobs) queuing a run creates
      the correct notification for the owner with a working deep link.
- [ ] Muting the Pipeline category suppresses these notifications.
- [ ] No notification is emitted for progress ticks.
- [ ] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/PipelineNotificationTest.php`: transitions create the
  expected `NotificationType`; preference off suppresses; no progress
  notifications.
- **Feature** `tests/Feature/Pipeline/PipelineNotificationFeatureTest.php`:
  a finished job appears in `/notifications` for the owner and its link resolves.

## 9. Notes & open questions

- Decide the "long job" threshold for the queued notification (e.g. estimated
  > 5 minutes); without an estimate, notify for imports over a size threshold.
- Consider bundling many runs from one plan into a single notification summary
  (PIPE-09 umbrella run) — likely needed once sync is in.
