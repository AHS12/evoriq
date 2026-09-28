# FND-04 — Charting foundation

- **Status:** Done
- **Epic:** foundations
- **Estimate:** L
- **Depends on:** FND-01, FND-02
- **Blocks:** FND-05, DASH-02, DASH-03, DASH-04, REP-*
- **TDR:** §28–32

## 1. Why

Dashboards, reports and comparisons are all chart-driven, but the product has no
charting layer yet. Choosing a library per page would produce inconsistent
colours, tooltips, axis formatting, loading and empty states — and would break
in the four palettes, dark mode and high contrast. One themed, typed foundation
means every later chart surface looks and behaves the same and reuses FND-02 for
formatting.

## 2. Scope

**In**

- Chart library choice: **Recharts v3** wrapped by the shadcn `chart` primitive
  (`components/ui/chart.tsx`), themed through the existing `--chart-1…5` CSS
  variables.
- Shared, typed chart components: `LineChart`, `BarChart`, `AreaChart`,
  `DonutChart`, plus a `ChartCard` frame (title/description/actions).
- Shared series config: `ChartSeries` → `ChartConfig`, stacked series, explicit
  or token-derived colours.
- Axis, grid, tooltip and legend defaults; tick formatting hooks that use
  `lib/format.ts`.
- Animation that respects `prefers-reduced-motion`.
- Loading (`ChartSkeleton`, FND-01) and empty (`EmptyState`) states.
- Accessible containers (`role="img"` + label).

**Out**

- Specific dashboards/reports and their data (DASH-*, REP-*, ANA-*).
- Metric cards, deltas and sparklines (FND-05).
- Server-side chart rendering for PDF exports (EXP-01 decides separately).

## 3. Data model

None. Charts consume plain arrays from page props.

## 4. Backend

None.

## 5. Frontend / UI

**Decision — library:** Recharts is the shadcn-standard charting library, ships
`ChartContainer`/`ChartTooltip`/`ChartLegend` primitives that read our CSS
variables, and supports React 19. `components/ui/chart.tsx` is published from the
shadcn registry (import paths adapted) and treated as a primitive.

**Files**

- `components/ui/chart.tsx` — shadcn primitive (do not hand-edit).
- `components/charts/types.ts` — `ChartDatum`, `ChartSeries`, `BaseChartProps`.
- `components/charts/series.ts` — token colouring + `ChartConfig` builder.
- `components/charts/chart-frame.tsx` — loading/empty/surface wrapper.
- `components/charts/chart-card.tsx` — titled chart card.
- `components/charts/line-chart.tsx`, `bar-chart.tsx`, `area-chart.tsx`,
  `donut-chart.tsx`.
- `hooks/use-reduced-motion.ts`.

**Experience**

- Series colours follow `--chart-1…5` in order and remain legible in every
  palette, dark mode and high contrast (tokens already cover this).
- Axes are quiet: no axis line, dashed horizontal grid, muted tick text; ticks
  formatted by the caller (dates via `formatDate`, hours via `formatHours`).
- Tooltip shows the series label + formatted value; legend is optional.
- Loading shows `ChartSkeleton`; no data shows `EmptyState` — never an empty
  frame or a broken axis.

### A11y & i18n

- The chart container carries `role="img"` and a label built from the series
  titles (or an explicit `ariaLabel`).
- Empty/loading copy comes through `t()` and is added to all five dictionaries.
- Animations are disabled when the OS requests reduced motion.

## 6. API / routes / props

None.

## 7. Acceptance criteria

- [x] Recharts + the shadcn `chart` primitive are installed and published.
- [x] `LineChart`, `BarChart`, `AreaChart` and `DonutChart` exist with typed
      props and render from a `ChartDatum[]` + `ChartSeries[]`.
- [x] Series colours derive from the chart tokens and read correctly in dark,
      khaki, dracula and high-contrast palettes.
- [x] Loading and empty states are built in; animations respect reduced motion.
- [x] Axis/tooltip values are formatting-ready via `lib/format.ts`.
- [x] No hard-coded English (empty labels translated).
- [x] `composer check` and `npm run build` pass.

## 8. Tests

- No JS test runner yet (FND-09). Verified by `npm run types:check`,
  `npm run check` and `npm run build`. When FND-09 lands, add render tests for
  each wrapper (loading, empty, populated) and the series-config helper.
- A consuming Inertia feature test is added when the first analytics page lands
  (DASH-02).

## 9. Notes & open questions

- **Files:** `components/ui/chart.tsx` (shadcn, recharts v3.10),
  `components/charts/{types,series,chart-frame,chart-card,chart-tooltip,line-chart,bar-chart,area-chart,donut-chart}`,
  `hooks/use-reduced-motion.ts`; conventions documented in `docs/ui-conventions.md`.
- Keep `components/ui/chart.tsx` pristine so `shadcn add` can refresh it;
  behaviour lives in `components/charts/*`.
- Recharts `ResponsiveContainer` is provided by `ChartContainer`; wrappers pass
  sizing through `className` (default `aspect-video`).
- **Tooltip values** route through `valueFormatter` (a small `ChartValueTooltip`
  wrapper); axes through `xTickFormatter`/`yTickFormatter`.
- Deferred: brush/zoom, reference lines, annotations, and a text-table fallback
  for screen readers (revisit with FND-10 accessibility audit).
