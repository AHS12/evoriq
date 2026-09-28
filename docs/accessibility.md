# Accessibility & high-contrast audit

Companion to **FND-10** (`spec/foundations/FND-10-accessibility-audit.md`). This
is the repeatable record for the keyboard/focus/contrast pass across the app.
Run it whenever a new surface ships, and extend the findings table.

## Checklist

| Area             | What we check                                                              |
| ---------------- | -------------------------------------------------------------------------- |
| Landmarks & skip | one `<main>` per page, `header`/`nav` landmarks, working "Skip to content" |
| Keyboard         | every interactive control is reachable; no focus traps outside dialogs     |
| Focus visibility | `focus-visible` ring on controls; nothing suppresses outlines globally     |
| Names & roles    | icon-only controls labelled, inputs tied to labels, dialogs titled         |
| Colour contrast  | text/UI tokens pass in light, dark, khaki, dracula and high contrast       |
| Motion           | reduced motion honoured (FND-01); no information carried only by motion    |
| Live regions     | long-running updates announced politely, never per progress tick           |

## How to run it

1. Tab through the app from a fresh load — the first stop is **Skip to content**;
   activating it moves focus into `<main id="main-content">`.
2. Reach every action by keyboard only (open dialogs/menus with Enter/Space,
   close with Esc, confirm focus returns to the trigger).
3. Toggle each appearance mode (light, dark, system), each palette
   (default/khaki/dracula) and **High contrast**, scanning text, borders, focus
   rings, badges and charts.
4. Enable the OS "reduce motion" setting and confirm ambient animation stops
   while status remains readable.
5. For icon-only buttons, confirm an accessible name is announced.

## Findings & fixes

| Finding                                                                 | Fix                                                                 |
| ----------------------------------------------------------------------- | ------------------------------------------------------------------- |
| Mobile navigation trigger had no accessible name.                        | Added `aria-label` (`Navigation menu`).                             |
| High contrast omitted ring/destructive/success/chart tokens.             | Defined them for light and dark high-contrast blocks in `app.css`.  |
| No skip path existed; `<main>` had no stable id.                         | Added `SkipToContent` + `id="main-content"` on the landmark.        |
| Long-running status already used `LiveRegion`/`InlineAlert` (FND-01/06). | Verified; no change needed.                                         |
| Theme, language, notification and palette triggers already labelled.     | Verified; no change needed.                                         |

## Tokens

- High contrast applies on top of the active palette via
  `data-contrast="high"` on `<html>` and now overrides chart, ring,
  destructive, success, warning and info colours — so charts and focus rings
  are chosen for maximum legibility instead of inheriting a decorative palette.
- Motion tokens and the `prefers-reduced-motion` reset live in `app.css`
  (FND-01); see [`ui-conventions.md`](ui-conventions.md).

## Follow-ups

- Consider adding `vitest-axe` or a pa11y step to the quality gate once the
  surface count grows; the manual checklist is the baseline for now.
- Chart screen-reader data-table fallback is tracked in FND-04 notes.
