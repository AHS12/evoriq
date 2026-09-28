# FND-09 — Frontend test runner (`vp test` + Vitest)

- **Status:** Done
- **Epic:** foundations
- **Estimate:** M
- **Depends on:** —
- **Blocks:** test coverage for FND-04, FND-05, FND-06, FND-07 and every later page
- **TDR:** —

## 1. Why

The frontend has **no test runner**: formatters, date-range math, series
configuration and chart state logic are only guarded by `tsc` and `oxlint`, which
cannot catch behavioural regressions. FND-04 explicitly deferred render tests
"until FND-09", and the upcoming pipeline specs (PIPE-02/03) add real JavaScript
logic (run aggregation, adaptive polling) that must be unit-tested. Vite+ already
bundles Vitest, so the runner is purely configuration plus conventions.

## 2. Scope

**In**

- A Vitest project configured through the existing Vite+ toolchain (`vp test`),
  using `jsdom`.
- A shared setup file: `@testing-library/jest-dom` matchers, RTL cleanup and
  `jsdom` polyfills (`matchMedia`, `ResizeObserver`) needed by Recharts/FND-01.
- RTL helpers: a provider-wrapping `render`, `renderWithUser`, and a reusable
  `useTranslation` mock module for i18n-dependent components.
- Wiring `npm test` into `composer check` so a failing test fails the gate.
- Initial coverage for existing foundations: `lib/format.ts`,
  `lib/date-range.ts`, `components/charts/series.ts`, `ChartFrame` and the
  `LineChart` wrapper's loading/empty/surface states.

**Out**

- End-to-end / browser (Playwright, WebdriverIO) tests — a later concern.
- Coverage thresholds and CI reporting (can be enabled later with
  `@vitest/coverage-v8`).
- Full populated-chart SVG assertions: Recharts measures its container, so
  jsdom cannot render a meaningful chart; those are verified by sister Inertia
  feature tests once data pages land.

## 3. Toolchain & configuration

Vite+ owns dev/build/lint/fmt; it also bundles Vitest. Tests therefore run
through `vp test`, configured in a **dedicated `vitest.config.ts`** so the
Laravel, Inertia and Wayfinder Vite plugins do not load during unit tests.

```ts
// vitest.config.ts
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite-plus';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [react()],
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/test/setup.ts'],
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        clearMocks: true,
        restoreMocks: true,
        css: false,
    },
});
```

- `globals: false` (default): tests import `describe/it/expect/vi` from
  `vitest`, so no global type plumbing is required in `tsconfig.json`.
- `restoreMocks`/`clearMocks` reset spies between tests.
- `css: false` skips Tailwind processing; component tests assert behaviour, not
  computed styles.

## 4. Tests layout & helpers

Tests are **co-located** with the code they cover (`*.test.ts` / `*.test.tsx`
next to the module) and live inside `resources/js`, so `tsc --noEmit` type-checks
them.

| Concern            | Path                                        |
| ------------------ | ------------------------------------------- |
| Setup (env/matchers) | `resources/js/test/setup.ts`              |
| RTL helpers        | `resources/js/test/render.tsx`              |
| i18n mock          | `resources/js/test/support/translation-mock.ts` |

- `setup.ts` imports `@testing-library/jest-dom/vitest`, runs `cleanup()` after
  each test and stubs `window.matchMedia` + `ResizeObserver` (absent in jsdom,
  used by `useReducedMotion` and Recharts).
- `render.tsx` wraps UI in the providers components commonly assume
  (`TooltipProvider`) and exports `render`, `renderWithUser` (returns a
  `userEvent` instance) plus the usual RTL re-exports.
- `translation-mock.ts` exposes a `useTranslation` standing in for the real
  hook (which reads Inertia page props). A test opts in with
  `vi.mock('@/hooks/use-translation', async () => await import('@/test/support/translation-mock'))`
  and can seed a dictionary via `setTranslations()`.

## 5. Scripts & quality gate

`package.json`:

```jsonc
"test": "vp test",
"test:watch": "vp test --watch"
```

`composer check` gains `npm run test` (between `types:check` and the PHP gate),
so the full gate is: frontend format/lint → TypeScript → **frontend tests** →
Pint → PHPStan → Pest. `AGENTS.md` §8.10 is updated to document the runner and
the co-location/helper conventions.

## 6. Initial coverage

- `lib/format.test.ts` — placeholder handling, durations, hours, percent,
  bytes, compact numbers, month labels, throughput.
- `lib/date-range.test.ts` — preset resolution with an injected `now`, ISO
  (de)serialization, granularity, previous-period/last-year comparisons.
- `components/charts/series.test.ts` — token cycling and config derivation.
- `components/charts/chart-frame.test.tsx` — loading skeleton, empty state.
- `components/charts/line-chart.test.tsx` — loading, empty (uses the
  translation mock) and the rendered surface (`role="img"` label).

## 7. Acceptance criteria

- [x] `vp test` runs the suite in `jsdom` and `npm test` is part of
      `composer check`.
- [x] `@testing-library/jest-dom` matchers and RTL cleanup work in every test.
- [x] `matchMedia`/`ResizeObserver` polyfills let chart components mount.
- [x] Helpers exist for provider-wrapped rendering and i18n mocking.
- [x] The initial suite covers the modules listed in §6 and passes.
- [x] `composer check` (including the new frontend tests) passes.

## 8. Tests

This spec *is* the test infrastructure; its own verification is that the suite
runs green inside `composer check` and fails the gate when a test fails.

## 9. Notes & open questions

- **Dependencies:** `jsdom` 29, `@testing-library/react` 16, `@testing-library/jest-dom`
  7 (plus the already-present `@testing-library/user-event` 14 / `dom` 10); Vitest
  comes bundled with `vite-plus`.
- **Files:** `vitest.config.ts`, `resources/js/test/{setup.ts,render.tsx,support/translation-mock.ts}`,
  tests in `resources/js/lib/{format,date-range}.test.ts` and
  `resources/js/components/charts/{series,chart-frame,line-chart}.test.*`.
- **Coverage:** enabling `@vitest/coverage-v8` and a threshold is deferred until
  the suite is large enough to be meaningful.
- **Inertia internals:** `@inertiajs/react` does not export its `PageContext`,
  so i18n-dependent components are tested against the `useTranslation` mock
  rather than a fake Inertia page.
- Keep tests behaviour-focused; avoid asserting Tailwind classes.
