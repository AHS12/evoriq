# UI conventions: motion & feedback

Companion to **FND-01** (`spec/foundations/FND-01-motion-and-feedback.md`).
This is the shared vocabulary for how the interface moves and how it tells the
user that something is happening. Keep it purposeful and short.

## Motion tokens

Defined in `resources/css/app.css` under `@theme` (Tailwind v4). Use the
utilities, never raw millisecond values.

| Token                       | Utility class       | Value   |
| --------------------------- | ------------------- | ------- |
| `--transition-duration-instant` | `duration-instant` | 90ms    |
| `--transition-duration-fast`    | `duration-fast`    | 140ms   |
| `--transition-duration-base`    | `duration-base`    | 200ms   |
| `--transition-duration-slow`    | `duration-slow`    | 300ms   |
| `--ease-standard`               | `ease-standard`    | cubic-bezier(0.2, 0, 0, 1) |
| `--ease-emphasized`             | `ease-emphasized`  | cubic-bezier(0.2, 0, 0, 1.15) |
| `--ease-exit`                   | `ease-exit`        | cubic-bezier(0.4, 0, 1, 1) |

Two composed utilities cover the common case:

- `transition-smooth` — colors, background, border, shadow, opacity and
  transform over `--transition-duration-base` with `--ease-standard`.
- `transition-smooth-fast` — the same properties at `--transition-duration-fast`
  (row hover, small controls).

Guidance: UI state changes 120–220ms; layout/reveal 200–320ms; always
interruptible. Long-running work uses **ambient** motion only (progress
shimmer, pulsing live dot) — never motion that competes with reading.

## Reduced motion

`app.css` strips non-essential animation and transition duration when the OS
requests reduced motion. Use the Tailwind variants for targeted intent:

- `motion-safe:animate-*` — run only when motion is allowed (preferred for
  ambient/pulsing decoration, e.g. `LiveDot`).
- `motion-reduce:animate-none` / `motion-reduce:transition-none` — opt a
  specific element out (e.g. a progress bar that should snap).

## Feedback primitives

All live in `resources/js/components/feedback/`.

| Component | Use for |
| --------- | ------- |
| `TableSkeleton` / `TableBlockSkeleton` | table-shaped loading (`TableSkeleton` renders placeholder `TableRow`s inside an existing `TableBody`) |
| `ListSkeleton` | row/list surfaces (job activity, feeds) |
| `CardSkeleton` | KPI / metric card grids |
| `ChartSkeleton` | data-viz panels |
| `LiveDot` | pulsing live status (pipeline indicator, run headers); static when inactive and under reduced motion |
| `LiveRegion` | visually hidden `aria-live="polite"` region for status text |

### Skeleton vs spinner

- **Skeleton** when the shape of the incoming content is known and the wait is
  more than a blink (tables, lists, cards, charts). Prefer it for first loads
  and filter changes.
- **Spinner** for work whose result does not change layout — a submit button, a
  row-level action, a small inline refresh. Use the `Button` `loading` prop so
  the spinner is bound to the button's state.

### Buttons

`Button` accepts a `loading` prop. It renders a `Spinner`, sets `aria-busy` and
disables the button. Do not hand-roll `processing && <Spinner />` plus
`disabled={processing}`. For `asChild` buttons the spinner is not injected (the
slot owns the element) — `aria-busy`/`disabled` are still applied.

### Toasts

Import `toast` from `@/lib/toast`; never import `sonner` directly. It
centralises tones (`success`, `info`, `warning`, `error`), per-tone durations
(4s success/info, 6s warning, 8s error), `id`-based dedupe and an optional
`action`. Pass a stable `id` (e.g. `job-${id}`) when the same entity can
transition more than once, so repeated toasts replace rather than stack.

### Live regions

Long-running updates are announced politely, throttled to **stage changes and
completion only** — never every progress tick. Wrap the region with
`aria-live="polite"` (or render `LiveRegion`) and toggle `aria-busy` while work
runs. Never rely on color or motion alone: pair state with an icon and text.

### Optimistic updates

For list mutations (cancel, retry, delete), update locally first, keep the row
visible with an inline pending state (spinner/disabled), and reconcile on
success. On failure, revert the row and surface an error toast with an explicit
retry action — do not leave the list in an ambiguous state or block the page
with a global spinner.
