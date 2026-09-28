# FND-02 — Formatting utilities

- **Status:** Done
- **Epic:** foundations
- **Estimate:** S
- **Depends on:** —
- **Blocks:** FND-03, FND-05, PIPE-02, PIPE-05, PIPE-09, analytics/report specs
- **TDR:** §28–32

## 1. Why

Hours, durations, percentages, currency and byte counts are shown everywhere —
pipeline progress, dashboard KPIs, reports, exports. Today formatting is
scattered (`components/data-processing/job-utils.ts` has `formatNumber`,
`formatBytes`, `relativeTime`, `computeEta`). Duplicated, inconsistent
formatting makes the product feel cheap and breaks locale behaviour. This spec
creates one locale-aware source of truth.

## 2. Scope

**In**

- `resources/js/lib/format.ts` with pure, locale-aware formatters driven by
  `appLocale()` from `lib/locale.ts`:
  - `formatNumber(value, { compact? })`
  - `formatDuration(seconds)` → `0m`, `45m`, `1h 5m`, `2h 0m`, `1d 3h`
  - `formatClock(seconds)` → `04:32`, `1:02:03` (for elapsed timers)
  - `formatHours(seconds | decimalHours)` → `1,284 h` / `1,284.5 h`
  - `formatPercent(value, { digits? })` → `78%`, `78.4%`
  - `formatCurrency(amount, currency)` → `$1,234.50`
  - `formatBytes(bytes)`
  - `formatRelativeTime(value)` (move from job-utils)
  - `formatDateTime(value)` / `formatDate(value)` / `formatTime(value)`
  - `formatThroughput(records, seconds)` → `1,240 rows/s`
- Null/undefined handling: every formatter returns a consistent placeholder
  (`—`) rather than `NaN`/`Invalid Date`.
- Migrate `job-utils.ts` to re-use `lib/format.ts` (keep `t`-dependent helpers
  like `resultSummary`/`groupJobsByDay` where they are).

**Out**

- Server-side formatting (later specs may mirror rules in PHP; not here).
- Timezone conversion logic (a later analytics concern) — formatters render the
  `Date`/instant they are given.

## 3. Data model

None.

## 4. Backend

None. (Rule: the server sends raw values — seconds, amounts, ISO timestamps —
never pre-formatted strings, so the client can localize.)

## 5. Frontend / UI

**Experience** — N/A (utility). Contract matters:

- Duration rule: `< 1h` → minutes; `< 1d` → `Xh Ym`; `≥ 1d` → `Xd Yh`.
  Never `0h 0m` for a non-zero value; support `0m`.
- Numbers use grouping by default; `compact` gives `1.2K`, `3.4M` for tight
  spaces (sparklines, badges).
- Currency uses the workspace currency when provided, otherwise the app
  default; always 2 decimals unless the value is a whole large amount in
  compact contexts.
- All output is locale-driven (`appLocale()`), so switching locale changes
  formatting after the full reload.

### A11y & i18n

- Formatters are pure functions; no hooks. Components that need reactivity to
  locale changes re-render on full reload (existing behaviour).
- Do **not** hard-code English unit words (`hours`, `min`) in formatters —
  either use `Intl` units or expose a `t`-aware wrapper for unit labels.

## 6. API / routes / props

None.

## 7. Acceptance criteria

- [x] `lib/format.ts` exports the functions above with documented signatures.
- [x] `job-utils.ts` no longer declares its own `formatNumber`/`formatBytes`/
      `relativeTime` (re-exports from `lib/format.ts`).
- [x] Every formatter returns the placeholder for null/invalid input.
- [x] No component formats durations/numbers inline with `Intl` anymore where a
      helper exists (date/time helpers and byte sizes migrated).
- [x] Switching locale updates all formatted output (formatters read
      `appLocale()` on every call; locale switch reloads the page).

## 8. Tests

- Prefer adding these under FND-09 (Vitest): table-driven tests for
  `formatDuration` (0, 59, 60, 3599, 3600, 86399, 86400), `formatPercent`
  rounding, `formatBytes`, and null handling.
- Until FND-09: relied on by feature tests indirectly (page renders without
  throwing).

## 9. Notes & open questions

- **Names:** `relativeTime` became `formatRelativeTime`; `job-utils.ts`
  re-exports it under the old name so pipeline call sites keep working.
- **Migrated call sites:** users, roles, files, audit logs pages; role/user
  detail sheets; backup/command run lists; backup & developer settings — all
  now use `formatDate` / `formatDateTime`; byte sizes use `formatBytes`
  (`file-utils.formatSize` and the backup list now delegate to it).
- **Behaviour tweaks:** `formatBytes(0)` now renders `0 B` (was `—`), and
  `formatDuration` renders `0m` for sub-minute values, per the duration rule.
- **Left as-is:** `job-utils.dayLabel` keeps its `appLocale()` weekday/day
  formatting because it is a `t`-aware grouping helper deliberately kept there.
- Decide whether duration output should always carry a unit (`1h 5m`) or compact
  (`1:05`) in table cells — keep both (`formatDuration` vs `formatClock`).
- Consider a single `format(type, value)` façade later if call sites get noisy.
