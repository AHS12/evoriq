# FND-10 — Accessibility & high-contrast audit

- **Status:** Done
- **Epic:** foundations
- **Estimate:** M
- **Depends on:** FND-01..06
- **Blocks:** —
- **TDR:** §30, §39, §45

## 1. Why

The foundations now provide motion, formatting, ranges, charts, states, tables and
a command palette, but accessibility has been handled per-component rather than
audited as a whole. Several primitives (icon-only controls, the skip path, the
high-contrast palette) were never verified together, and high contrast only
overrides part of the token set — charts, ring, destructive and success colours
still fall back to the active palette. This spec closes the gap with one
keyboard/focus/contrast pass and a repeatable record, so later phases inherit a
compliant baseline instead of accruing debt.

## 2. Scope

**In**

- A **skip-to-content** link, first in the tab order, targeting the app's
  `<main>` landmark; the landmark gets a stable `id`.
- **High-contrast token completeness**: add `--ring`, `--destructive`,
  `--success` and `--chart-1…5` (and their foregrounds where applicable) to both
  `html[data-contrast='high']` blocks so charts and focus rings are legible, not
  inherited from the palette.
- A **control audit** across existing surfaces: every icon-only control has an
  accessible name; the mobile navigation trigger is fixed.
- A written **audit record** (`docs/accessibility.md`) capturing the checklist,
  findings and fixes, linked from `docs/ui-conventions.md`.
- A Vitest guard for the skip link.

**Out**

- A third-party axe/pa11y CI integration (candidate follow-up; the manual
  checklist is the deliverable here).
- Rewriting shadcn primitives (they already ship correct roles/labels).
- Screen-reader testing on real hardware, and WCAG statement/legal compliance.
- Chart data-table fallbacks (tracked in FND-04 notes).

## 3. Data model

None.

## 4. Backend

None.

## 5. Frontend / UI

**Files**

- `components/app/skip-to-content.tsx` — visually hidden anchor that becomes
  visible on focus, `href="#main-content"`.
- `components/app-shell.tsx` — renders the skip link ahead of the layout.
- `components/app-content.tsx` — the `<main>`/`SidebarInset` gets
  `id="main-content"` (overridable by a caller).
- `resources/css/app.css` — high-contrast token completion.
- `components/app-header.tsx` — the mobile menu trigger gets an accessible name.
- `docs/accessibility.md` — the audit record.

**Checklist (audit record)**

| Area                | What is checked                                                |
| ------------------- | -------------------------------------------------------------- |
| Landmarks & skip    | one `<main>`, header/nav landmarks, working skip link          |
| Keyboard            | every interactive control reachable; no focus traps outside dialogs |
| Focus visibility    | `focus-visible` ring on controls; not suppressed globally      |
| Names & roles       | icon-only buttons labelled; inputs tied to labels; dialogs titled |
| Colour & contrast   | text/UI tokens pass in light, dark, khaki, dracula, high contrast |
| Motion              | reduced-motion honoured (FND-01) and no essential info in motion |
| Live regions        | long-running updates announced politely, not per tick          |

**Findings & fixes (this pass)**

- Mobile navigation trigger (`app-header.tsx`) had no accessible name — fixed
  with `aria-label`.
- High-contrast blocks omitted chart/ring/destructive/success tokens — fixed.
- No skip link existed — added, wired to `#main-content`.
- Theme/language/notification/palette triggers already name themselves; verified.

### A11y & i18n

- The skip link is the first focusable element and has visible focus styling.
- New strings (`Skip to content`) go to all five `lang/app/*.json`.
- High-contrast values are chosen to exceed WCAG AA for the surfaces that use
  them (black/white text on solid chart fills; ring at full foreground).

## 6. API / routes / props

None.

## 7. Acceptance criteria

- [x] A "Skip to content" link is the first tab stop and moves focus to `<main>`.
- [x] The `<main>` landmark exposes `id="main-content"` in both layouts.
- [x] Every icon-only control has an accessible name (mobile menu fixed).
- [x] High-contrast mode defines ring, destructive, success and chart tokens for
      light and dark.
- [x] `docs/accessibility.md` records the checklist and the fixes.
- [x] `docs/ui-conventions.md` links to the audit.
- [x] Vitest covers the skip link.
- [x] `composer check` passes.

## 8. Tests

- `components/app/skip-to-content.test.tsx` — accessible name, `#main-content`
  target, hidden-until-focus classes present.
- No backend tests.

## 9. Notes & open questions

- A future spec could add `vitest-axe`/pa11y to the gate for regression
  protection; this pass documents the baseline instead of adding a heavy
  dependency.
- Contrast changes are CSS-only; verify visually in all four palettes plus high
  contrast before shipping palette changes.
- Skip-link target uses an `id` on the landmark rather than `tabIndex=-1` hacks;
  all modern browsers move focus to a fragment target with `id`.
