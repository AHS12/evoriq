# FND-01 — Motion & feedback system

- **Status:** Done
- **Epic:** foundations
- **Estimate:** M
- **Depends on:** —
- **Blocks:** PIPE-04, PIPE-05, PIPE-06, FND-05, FND-06
- **TDR:** §30, §35–38

## 1. Why

The product's differentiator is a calm, fast, *alive* interface — especially
while long background work runs. Right now motion is ad-hoc (`tw-animate-css`
utilities sprinkled per component) and feedback patterns (loading, pending,
success) differ between screens. A single motion + feedback vocabulary makes the
whole app feel intentional and makes the pipeline UI (PIPE) buildable from
shared parts.

## 2. Scope

**In**

- Motion **tokens** (durations + easings) as CSS variables and Tailwind theme
  entries, with light/dark/theme/high-contrast variants where relevant.
- A global `prefers-reduced-motion` policy that disables non-essential motion.
- Documented transition conventions for: dialogs/sheets, cards, list rows,
  timeline entries, progress bars, badges, page/section reveals.
- Shared feedback primitives:
  - `<PendingButton>` / `loading` button state (reuse `Spinner`).
  - `Skeleton` usage conventions (list, table, card, chart).
  - Toast conventions over `sonner` (tone, dedupe, action, duration).
  - `aria-live` polite regions convention for long-running updates.
- Optimistic-update guidance for list mutations (cancel/retry/delete).

**Out**

- Any third-party animation engine (`framer-motion`/`motion`) — stay CSS-based.
- Page-level route transitions (Inertia already handles these).
- New colors/themes (FND covers tokens only; palettes already exist).

## 3. Data model

None.

## 4. Backend

- None required. (Flash-toast infrastructure already exists via
  `use-flash-toast` / Inertia `flash` props.)

## 5. Frontend / UI

**Experience**

- Motion is **purposeful and short**: UI state changes 120–220 ms; layout/reveal
  200–320 ms; always interruptible.
- Long-running work uses **ambient** motion (a slow progress shimmer, a pulsing
  live dot), never motion that competes with reading.
- Feedback is immediate: a button that starts async work shows a spinner within
  one frame and is disabled; a list row that is mutating shows an inline pending
  state rather than a global spinner.

**Tokens** (add to `resources/css/app.css`, exposed via `@theme`):

```css
@theme {
    --duration-instant: 90ms;
    --duration-fast: 140ms;
    --duration-base: 200ms;
    --duration-slow: 300ms;

    --ease-standard: cubic-bezier(0.2, 0, 0, 1);
    --ease-emphasized: cubic-bezier(0.2, 0, 0, 1.15);
    --ease-exit: cubic-bezier(0.4, 0, 1, 1);
}
```

- Add utility helpers (e.g. `transition-smooth`) via `@utility` so components
  don't repeat `transition-[...] duration-200 ease-standard`.
- Reduced motion:

```css
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.001ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.001ms !important;
        scroll-behavior: auto !important;
    }
}
```

**Shared primitives to add/extract**

- `components/feedback/skeleton-*.tsx` — `ListSkeleton`, `TableSkeleton`,
  `CardSkeleton`, `ChartSkeleton` built on the existing `ui/skeleton`.
- `components/feedback/live-dot.tsx` — a small pulsing status dot used by the
  pipeline indicator and run headers (respects reduced motion; static when off).
- `components/ui/button.tsx` extension: a `loading` prop that renders `Spinner`
  and sets `aria-busy` + `disabled`.
- Toast helper `lib/toast.ts` wrapping `sonner` with our tones (`success`,
  `info`, `warning`, `error`), `id`-based dedupe and optional action button.

**Conventions to document** in `AGENTS.md` §8 (small edit) or a short
`docs/` note referenced from the spec: when to use skeleton vs spinner, toast
duration rules, and the `aria-live` region pattern.

### A11y & i18n

- Never rely on color/motion alone to convey state — pair with icon + text.
- All animated status text lives in `aria-live="polite"` containers; do not
  announce every progress tick (throttle announcements to stage changes /
  completion).
- Any new label string is added to all five `lang/app/*.json`.

## 6. API / routes / props

None.

## 7. Acceptance criteria

- [x] Motion tokens exist and are used by at least one refactored component.
- [x] `prefers-reduced-motion` disables ambient/shimmer animation app-wide.
- [x] `Button` supports a `loading` prop used on all submit buttons in
      refactored surfaces.
- [x] Skeleton components exist and are used by the pipeline + one list page.
- [x] Toast tones/dedupe are centralized; no component calls `toast` with raw
      options.
- [x] No `framer-motion`/`motion` dependency added.
- [x] Docs snippet explains the conventions.

## 8. Tests

- No JS test runner yet (FND-09). Verified via `npm run types:check`,
  `npm run check`, `npm run build` (tokens/utilities compile) and the backend
  Inertia feature suite (`composer check`). When FND-09 lands, add unit tests
  for the toast helper and the reduced-motion guard if it has logic.

## 9. Notes & open questions

- **Token names:** Tailwind v4 exposes durations through the
  `--transition-duration-*` namespace (`duration-fast`, …) and easings through
  `--ease-*` (`ease-standard`, …); `transition-smooth` /
  `transition-smooth-fast` compose them. The spec's `--duration-*` names are
  realised through that namespace so the utilities are generated.
- **`ui/button.tsx` edit:** the `loading` prop was added in place (additive and
  backwards compatible) as the spec requires, despite the general "do not edit
  `components/ui/*`" rule. A future shadcn re-publish would drop it; re-apply.
  `asChild` buttons must keep a single element child, so the spinner is only
  injected for real `<button>`s (slotted buttons get `aria-busy` + `disabled`
  only).
- **Wiring:** `ListSkeleton` is used on `/activity`, `TableSkeleton` by the
  shared `DataTable` (users, roles, audit logs, files), `ChartSkeleton` /
  `CardSkeleton` are ready for FND-04/FND-05. `LiveDot` is wired into the
  activity "Live" toggle; `LiveRegion` is available for PIPE.
- **Reduced motion:** `motion-safe:animate-pulse` is the preferred pattern for
  ambient pulsing; the global media query is the safety net.
- Confirm `tw-animate-css` is enough for entrance/exit of Radix primitives; if
  not, extend with data-state variants rather than adding a lib.
- Decide whether tokens should also drive `view-transition-name` for
  cross-route continuity (defer).
