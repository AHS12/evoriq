# PIPE-04 — Timeline UI primitive

- **Status:** Draft
- **Epic:** pipeline
- **Estimate:** M
- **Depends on:** FND-01
- **Blocks:** PIPE-05, PIPE-06, PIPE-09, REP-07
- **TDR:** §35–38

## 1. Why

The "best timeline like UI" needs a reusable, accessible timeline primitive
instead of a one-off. It will render pipeline events (PIPE-05/06), import stages
(PIPE-09) and later historical comparisons. Existing "timeline-ish" code is
ad-hoc meta rows inside `job-detail-sheet.tsx`. Building one good primitive
keeps all timelines consistent and makes them fast to compose.

## 2. Scope

**In**

- `components/timeline/` primitives:
  - `Timeline` — vertical event timeline (rail + marker + time gutter + content),
    with optional day groupings and sticky group labels.
  - `TimelineItem` — one entry: tone, marker, time, optional duration,
    expandable detail, running state.
  - `TimelineTrack` — horizontal proportional track (segmented progress / mini
    Gantt) used for stage breakdowns.
- Density variants (`compact`, `comfortable`), tone tokens, running/pulsing
  state, entrance animation (reduced-motion aware).
- Accessible semantics and keyboard support for expandable items.
- Skeleton + empty variants.

**Out**

- Data fetching (presentational only).
- Virtualization (add later if a run exceeds a few hundred visible rows; mark as
  follow-up).
- Charting (FND-04).

## 3. Data model

None.

## 4. Backend

None.

## 5. Frontend / UI

**Files**

```text
resources/js/components/timeline/
├── timeline.tsx           # <Timeline>, <TimelineGroup>
├── timeline-item.tsx      # <TimelineItem>, <TimelineMarker>, <TimelineTime>, <TimelineContent>
├── timeline-track.tsx     # <TimelineTrack>, <TimelineSegment>
├── timeline-skeleton.tsx  # loading placeholder
└── timeline-utils.ts      # tone → class helpers, duration label via lib/format
```

**Vertical timeline API**

```tsx
type TimelineTone = 'neutral' | 'info' | 'success' | 'warning' | 'error' | 'muted';

<Timeline density="comfortable" aria-label={t('Pipeline events')}>
    <TimelineGroup label={t('Today')}>
        <TimelineItem tone="info" marker={<PlayIcon />} time="2026-09-27T01:14:02Z"
                      durationMs={21000} running={false} expandable defaultOpen={false}>
            <TimelineContent title={t('Reading source file')}
                             description={t(':count rows', { count: '12,000' })}>
                {/* expanded detail */}
            </TimelineContent>
        </TimelineItem>
    </TimelineGroup>
</Timeline>
```

- Rail is a 2 px line drawn with pseudo-elements; the last item's connector is
  clipped so it doesn't dangle.
- Marker is a toned circle with an icon; a `running` item shows an animated ring
  (static under reduced motion) and a subtle shimmer on its connector.
- Time gutter shows relative time (`formatRelativeTime`) and, on hover/focus,
  the absolute time via tooltip.
- `durationMs` renders as a right-aligned dim chip using `formatDuration`.
- Expandable items toggle with Enter/Space, expose `aria-expanded`, and animate
  open with the FND-01 tokens.

**Horizontal track API** (stage lanes / mini Gantt)

```tsx
<TimelineTrack
    segments={[
        { key: 'fetch', label: t('Fetch'), status: 'completed', ratio: 0.35 },
        { key: 'write', label: t('Write'), status: 'running', ratio: 0.65 },
    ]}
/>
```

- Segments are proportional to `ratio`; statuses map to tones.
- A running segment shows an indeterminate shimmer within its bounds; completed
  segments are solid; failed segments are error-toned and focusable to reveal
  the error in a tooltip.
- Renders an accessible summary (`role="list"` of segments) with labels so it is
  not colour-only.

**States**

- `TimelineSkeleton` mirrors the vertical layout (3–5 rows with rail + content
  blocks).
- Empty: hosts render `EmptyState`; the primitive itself renders nothing when
  `children` is empty and `emptyState` is not provided.

### A11y & i18n

- Root is `<ol>`; groups are `<li>` wrapping a `<ol>`; items are `<li>`; times
  are `<time dateTime>`.
- Markers are `aria-hidden`; their meaning is in the content text.
- Expandable items are buttons with `aria-expanded` + `aria-controls`.
- Allowed to pass `t`-translated labels; the primitive adds no untranslated
  text except optional decorative defaults.

## 6. API / routes / props

None.

## 7. Acceptance criteria

- [ ] `Timeline`/`TimelineItem`/`TimelineTrack` are used by PIPE-05 and at least
      one other surface (e.g. the run detail sheet).
- [ ] Running state animates and stops under `prefers-reduced-motion`.
- [ ] Expandable items are keyboard-operable with correct ARIA.
- [ ] Tones use semantic tokens and support all four themes + high contrast.
- [ ] Skeleton and empty variants exist and are used.
- [ ] No untranslated user-visible strings.

## 8. Tests

- No JS runner yet (FND-09). When available: render tests for item expansion,
  ARIA attributes, tone mapping, and `TimelineTrack` proportional widths.
- Until then: covered indirectly by page-level Inertia feature tests and manual
  review; note the manual checklist in the PR.

## 9. Notes & open questions

- Decide whether to virtualize from the start for long imports (10k+ events).
  Recommendation: cap the visible window server-side (PIPE-06 pagination) and
  virtualize only if measurements demand it.
- `TimelineTrack` may later become a general Gantt for scheduled-vs-actual
  (P2-01); keep the API minimal to allow that.
