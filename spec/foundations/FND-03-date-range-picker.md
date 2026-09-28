# FND-03 — Date-range & period picker

- **Status:** Done
- **Epic:** foundations
- **Estimate:** M
- **Depends on:** FND-02
- **Blocks:** FND-04, ANA-06, PIPE-09, REP-01, REP-07
- **TDR:** §10, §29, §31

## 1. Why

Every analytical surface is period-scoped (reports, dashboard, comparisons) and
the historical import is range-scoped (TDR §10). Clockify's own reporting is
limited and awkward across periods; a fast, obvious range picker with
presets + custom + comparison is a visible part of "better than Clockify".
One shared primitive avoids each page inventing its own date controls.

## 2. Scope

**In**

- A shared `<DateRangePicker>` supporting:
  - **Presets:** Today, Yesterday, This week, Last week, This month, Last month,
    This quarter, Last quarter, This year, Last year, Last 2 years, Last 5
    years, All time, Custom (TDR §31).
  - **Custom range** via a calendar (day range) with keyboard input fallback.
  - **Comparison** selector: none, Previous period, Same period last year,
    Custom — returns both the primary and comparison ranges.
  - Timezone-aware display (uses workspace time zone when provided).
- A normalized value type `DateRange { preset, start, end, granularity }` and a
  `ComparisonRange` companion.
- A compact variant (preset dropdown + date summary) for toolbars.
- URL/Inertia filter integration: serialized into query params via the existing
  `use-data-table-filters` pattern.

**Out**

- Analytics/comparison math (ANA-06) — this only produces ranges.
- Saved/reusable range presets (REP-09).
- Relative "rolling" ranges with time-of-day semantics beyond date boundaries.

## 3. Data model

None (client value object + query params). Server validates ranges in later
specs via FormRequest.

## 4. Backend

- None for the primitive itself. Later consumers (PIPE-09, REP-*) add
  `FormRequest`/FilterDTO validation for `start`/`end`/`preset`/`compare`.

## 5. Frontend / UI

**Decision — calendar dependency:** shadcn's `Calendar` is not installed. Add
`react-day-picker` (the shadcn-standard calendar) and publish the shadcn
`calendar` + `popover` composition, rather than hand-rolling a grid. Keep the
new `components/ui/calendar.tsx` as a standard shadcn primitive (do not hand-edit
beyond publishing).

**Files**

- `components/ui/calendar.tsx` (shadcn publish).
- `components/date-range/date-range-picker.tsx` — popover trigger + preset list
  + calendar + comparison section.
- `components/date-range/date-range-summary.tsx` — compact trigger label
  (`Sep 1 – Sep 30, 2026 · vs Sep 2025`).
- `components/date-range/period-presets.ts` — preset definitions (key, label
  key, resolver).
- `hooks/use-date-range.ts` — reads/writes range from Inertia filters, exposes
  helpers (`isCustom`, `label`, `toParams`).
- `lib/date-range.ts` — pure range math (`resolvePreset`, `previousPeriod`,
  `samePeriodLastYear`, `formatRangeLabel`).

**Experience**

- Trigger shows a clear summary, not a raw date input.
- Preset list is scrollable, grouped (`Relative`, `Calendar`, `History`) and
  marks the active preset.
- Selecting "Custom" reveals the calendar inline (popover stays open); range is
  applied on a primary "Apply" button so half-selected ranges don't refetch.
- Comparison toggle sits at the bottom; when enabled, a second range row
  appears with the smart default (previous period) and can be changed.
- Keyboard: arrow keys move days, Shift+arrows extend selection, Enter applies,
  Esc closes. Presets reachable via arrow keys.
- The range is reflected in the URL so links are shareable and back/forward
  works.

**States**

- Disabled when no workspace/data exists.
- "All time" shows the effective resolved bounds.
- Invalid custom range (end < start) is prevented in the UI and blocked on
  Apply.

### A11y & i18n

- Trigger is a button with `aria-haspopup="dialog"` and an accessible label
  including the current range.
- Calendar grid is keyboard navigable; selected/disabled days expose
  `aria-selected`/`aria-disabled`.
- All preset labels and UI strings added to the five `lang/app/*.json`
  dictionaries.

## 6. API / routes / props

- No new endpoints. Consumers put params on their existing index routes:
  `?period=last-month&start=…&end=…&compare=previous-period&compare_start=…&compare_end=…`.

## 7. Acceptance criteria

- [x] Presets listed in TDR §31 are all available and resolve correctly.
- [x] Custom range via calendar works with mouse and keyboard.
- [x] Comparison produces a correct previous-period and same-period-last-year
      range.
- [x] Range value is serialized to/from the URL and survives reload/back.
- [x] A compact summary is used in at least one toolbar.
- [x] No hard-coded English in the component.

## 8. Tests

- `lib/date-range.ts` is pure → unit tests (when FND-09 lands) for preset
  resolution, month/quarter/year boundaries, leap years, and both comparison
  strategies.
- Feature test: `tests/Feature/AuditLog/AuditLogControllerTest.php` —
  `date range filters narrow the listing and persist in the page props`
  (audit-logs is the first consumer, mapping to its `date_from`/`date_to`).

## 9. Notes & open questions

- **Implemented files:** `components/ui/calendar.tsx` (shadcn publish on
  `react-day-picker` v9 + `date-fns`), `components/date-range/date-range-picker.tsx`,
  `date-range-summary.tsx`, `period-presets.ts`, `hooks/use-date-range.ts`,
  `lib/date-range.ts`.
- **First consumer:** the audit-logs toolbar. The hook remaps params to
  `date_from`/`date_to`; `period`/`compare` ride along in the URL and the preset
  is re-derived from the dates when the server does not echo `period`.
- **All time:** resolves to an open range (no `start`/`end` params) until a
  consuming page exposes server-provided `min`/`max`; pass them to
  `resolvePreset`/`useDateRange` when available.
- **Ranges are date-only** (local midnight, inclusive). Serialization is local
  `YYYY-MM-DD` (never UTC) so picked days never shift.
- **Comparison** is produced by `resolveComparison`; the picker shows a live
  label and, for `custom`, a second calendar.
- Confirm the workspace time zone is available to the frontend as a shared prop
  (add in CONN/ENT specs); until then fall back to the app time zone setting.
- Decide fiscal-quarter support (defer).
