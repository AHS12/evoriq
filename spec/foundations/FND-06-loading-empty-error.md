# FND-06 — Loading / empty / error state system

- **Status:** Done
- **Epic:** foundations
- **Estimate:** S
- **Depends on:** FND-01
- **Blocks:** PIPE-04, PIPE-05, PIPE-06
- **TDR:** §30, §35–38

## 1. Why

Every async surface makes the same three decisions — *is it loading, is it
empty, did it fail* — and today each answers them differently: skeletons are
inconsistent, "nothing here" is a bespoke dashed box, failures are a truncated
red paragraph or a silently blank panel. The timeline primitive (PIPE-04) and
every run view (PIPE-05/06) reuse these states, so they must be one shared
vocabulary before the rail is built.

## 2. Scope

**In**

- A documented **state contract** (loading → skeleton, empty → `EmptyState`,
  error → `ErrorState`) with guidance on block vs inline.
- `components/feedback/inline-alert.tsx` — tone-aware inline message
  (`info`/`success`/`warning`/`error`) with optional title, action and dismiss.
- `components/feedback/error-state.tsx` — block-level failure with a retry
  action, optional collapsible raw details and a `compact` variant.
- `EmptyState` gains a `compact` variant for timeline/inline hosts.
- Semantic `--warning` / `--info` colour tokens (light, dark, high contrast).
- Adopt the primitives on real surfaces: the activity page's paused banner
  (`InlineAlert`) and the chart frame's failure state (`ErrorState`).
- A new "Loading / empty / error" section in `docs/ui-conventions.md`.

**Out**

- Data fetching, retry policy or polling logic — the primitives are
  presentational; `onRetry` is a caller callback (PIPE-03 owns transport).
- Route-level error pages (`pages/errors/error.tsx` already exists).
- New skeleton shapes beyond the FND-01 set.
- Empty/error copy authoring for future modules (PIPE-12 owns the glossary).

## 3. Data model

None.

## 4. Backend

None. `EmptyState`/`ErrorState`/`InlineAlert` receive already-translated strings
from their callers (or translate their own fixed labels via `useTranslation`).

## 5. Frontend / UI

**State contract**

| Condition      | Primitive                          | When                                   |
| -------------- | ---------------------------------- | -------------------------------------- |
| Loading        | `Skeleton` / `*Skeleton` (FND-01)  | shape is known and wait > a blink      |
| Empty (block)  | `EmptyState`                       | first load / filtered to nothing       |
| Empty (inline) | `EmptyState variant="compact"`     | inside a card, lane or sheet           |
| Error (block)  | `ErrorState`                       | a region failed to load; offer retry   |
| Error / notice | `InlineAlert`                      | contextual message inside a working UI |

Rules:

- Render **exactly one** of the four; never show a skeleton and an empty state
  together, and never leave a failed region blank.
- Pair state with **icon + text** — never colour alone.
- Errors state what happened and offer the next step (`onRetry` / `action`).

**`InlineAlert` API**

```tsx
type InlineAlertTone = 'info' | 'success' | 'warning' | 'error';

<InlineAlert
    tone="warning"
    title={t('Live updates are paused.')}
    action={<Button size="sm" onClick={resume}>{t('Resume')}</Button>}
    onDismiss={() => setDismissed(true)}
>
    {t('Progress will keep updating when you resume.')}
</InlineAlert>
```

- `role="status"` for `info`/`success`, `role="alert"` for `warning`/`error`.
- Tone maps to a semantic border/background/icon colour; default tone is `info`.
- Dismiss renders an icon button labelled `t('Dismiss')`.

**`ErrorState` API**

```tsx
<ErrorState
    title={t('Could not load this report')}
    description={t('The request failed. Try again in a moment.')}
    onRetry={refetch}
    retrying={isFetching}
    details={rawError}
    compact
/>
```

- `role="alert"`; retry uses the `Button` `loading` prop.
- `details` renders a native `<details>`/`<summary>` showing the raw message in
  a `<pre>`, labelled `t('Show details')` / `t('Hide details')`.
- `compact` shrinks padding/icon for in-card use; `action` adds a secondary
  control beside Retry.

**Tokens**

`--color-warning` / `--color-info` (+ `-foreground`) are added to `@theme` and
defined on `:root`, `.dark` and the two high-contrast blocks; palette themes
(dracula/khaki) inherit the base light/dark values. `destructive` and `success`
already exist.

### A11y & i18n

- Loading skeletons are `aria-hidden`; a single polite live region (FND-01
  `LiveRegion`) carries status text when a region is refreshing.
- `role="alert"` regions must not fire on every poll — reserve them for real
  failures.
- New fixed labels (`Dismiss`, `Show details`, `Hide details`) are added to all
  five `lang/app/*.json`; caller-supplied titles/descriptions are already
  translated via `t()`.

## 6. API / routes / props

None.

## 7. Acceptance criteria

- [x] `InlineAlert` supports all four tones, correct `role`, dismiss and action.
- [x] `ErrorState` supports title/description, retry (with loading), collapsible
      details and a compact variant.
- [x] `EmptyState` gains a working `compact` variant without changing existing
      call sites.
- [x] Warning/info tokens render correctly in light, dark and high contrast.
- [x] The activity paused banner uses `InlineAlert`; the chart frame renders
      `ErrorState` on failure.
- [x] Vitest coverage for all three primitives.
- [x] `composer check` passes.

## 8. Tests

- `resources/js/components/feedback/inline-alert.test.tsx` — tone → `role`,
  title/child rendering, dismiss callback + accessible label.
- `resources/js/components/feedback/error-state.test.tsx` — retry callback,
  `loading` button state, details disclosure, compact rendering.
- `resources/js/components/app/empty-state.test.tsx` — title/description/action
  and compact variant.
- `resources/js/components/charts/chart-frame.test.tsx` — extend with the error
  branch.
- No backend tests (presentational).

## 9. Notes & open questions

- **Tone tokens:** choosing representative warning/info values once (base
  light/dark + high contrast) keeps `app.css` small; revisit with per-palette
  tuning only if a palette looks off.
- **Details disclosure** uses native `<details>` (no JS state) so it stays
  accessible and dependency-free; the label swaps with `group-open:`.
- `EmptyState` keeps its `components/app/empty-state.tsx` path to avoid churn
  across existing imports; the feedback folder holds the newer primitives.
- PIPE-05/06 consume `ErrorState` for a failed run region (mapped
  `PipelineFailureReason` title/hint + `action`) and `InlineAlert` for
  backoff/reconnect notices.
