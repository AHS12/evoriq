# FND-08 — Command palette & navigation polish

- **Status:** Done
- **Epic:** foundations
- **Estimate:** M
- **Depends on:** FND-01
- **Blocks:** PIPE-08, future power-user flows
- **TDR:** §30, §34, §35

## 1. Why

Evoriq is keyboard-driven enough to feel fast, but today the primary "Search"
button in the header does nothing and there is no way to jump between sections
without the pointer. A single ⌘K palette over the app's navigation and a few
global actions is the cheapest, highest-leverage navigation improvement: it
makes every route reachable in two keystrokes, teaches the app's structure as
you type, and surfaces recent destinations. It also gives the pipeline epic a
natural home for future "go to run" / "retry last sync" commands.

## 2. Scope

**In**

- A global **command palette** opened with **⌘K / Ctrl+K**, Esc to close.
- **Navigation commands** derived from the same permission-gated navigation the
  sidebar renders, so it is impossible for the two to drift.
- **Action commands**: switch light/dark theme, open Account, open
  Notifications, log out.
- **Recent commands**: the last five executed commands, persisted per browser
  and re-surfaced at the top.
- **Triggers**: the existing header search button (currently inert) and an
  equivalent search trigger in the sidebar header, both showing the ⌘K hint.
- The palette mounted once at the app layout level, available on every
  authenticated page.
- A shared navigation builder so the sidebar and the palette consume one
  definition.

**Out**

- Searching records (users, projects, runs) — that needs server search and
  lands with the modules that own the data.
- Contextual/form actions (e.g. "New user") that require a page's dialog state.
- A user-customisable shortcut or command registry.
- Command analytics beyond the local recent list.

## 3. Data model

None server-side. Recent command ids are stored locally under
`evoriq.command.recent.v1` as an ordered array of `{ id, at }` (max 5).
Unknown ids are ignored when rendering.

## 4. Backend

None.

## 5. Frontend / UI

**Files**

- `components/ui/command.tsx` — shadcn primitive published on `cmdk` (do not
  hand-edit beyond the publish).
- `lib/app-navigation.ts` — `buildAppNavigation({ can, t, activeJobs })`
  returning the permission-gated `NavGroup[]`; the sidebar now uses it too.
- `lib/command-recents.ts` — pure, storage-injected recent-command store.
- `components/command-palette/command-palette.tsx` — the dialog + command
  groups, consuming `useCommandPalette`.
- `components/command-palette/command-palette-provider.tsx` — open/close state,
  the global ⌘K listener, and the palette host.
- `hooks/use-command-palette.ts` — `{ open, setOpen, toggle }` context reader.
- `components/app-header.tsx` / `components/app-sidebar-header.tsx` — the
  search trigger opens the palette.
- `layouts/app-layout.tsx` — mounts the provider.

**Experience**

- ⌘K (Ctrl+K on Windows/Linux) toggles the palette from anywhere; the search
  button toggles it with the pointer.
- Typing filters commands with cmdk's fuzzy matching; groups render as
  **Recent**, **Navigation**, **Actions**.
- Enter runs the highlighted command and closes the palette; arrow keys move.
- Navigation commands use Inertia visits (no full reloads); the palette closes
  before navigating so focus returns cleanly.
- The theme action flips between light and dark using the existing appearance
  store and label reads "Switch to dark mode" / "Switch to light mode".
- The palette is empty-state aware: a "No matching commands" row.

### A11y & i18n

- Built on `cmdk` inside the shadcn `Dialog`, so focus is trapped, background
  is inert and `aria-activedescendant` tracks the highlight.
- The dialog has an `sr-only` title/description; the input is labelled with a
  translated placeholder and a semantics-visible shortcut hint (`⌘K`).
- Fully keyboard reachable; the trigger buttons expose an `aria-label`.
- Every new string (`Command palette`, `Search commands…`, `Navigation`,
  `Actions`, `Recent`, `No matching commands`, `Switch to dark mode`,
  `Switch to light mode`, `Open command palette`, `Account`, `Notifications`,
  `Log out`, …) is added to all five `lang/app/*.json`.

## 6. API / routes / props

No new endpoints. Navigation uses existing Wayfinder helpers; actions use the
existing `logout` route and profile/notifications routes.

## 7. Acceptance criteria

- [x] ⌘K / Ctrl+K opens and closes the palette; Esc closes it.
- [x] The header and sidebar search triggers open it.
- [x] Navigation commands match the sidebar's permission-gated routes.
- [x] Filtering narrows commands; the empty state is shown when nothing matches.
- [x] Executing a command navigates or performs the action and closes the
      palette.
- [x] Recent commands persist (max five) and are surfaced under **Recent**.
- [x] Unknown/stale recent ids never break rendering.
- [x] Vitest coverage for the recents store and the palette interaction.
- [x] `composer check` passes.

## 8. Tests

- `lib/command-recents.test.ts` — push/dedupe/cap/order, malformed storage,
  injection.
- `components/command-palette/command-palette.test.tsx` — renders navigation and
  action groups, filters, executes a command, records a recent.
- `hooks/use-command-palette` behaviour exercised through the provider test.
- No backend tests (client-only navigation).

## 9. Notes & open questions

- **cmdk** is the shadcn-standard engine for the command primitive; no other
  new dependency.
- Recent ids are stable slugs (`nav:users`, `action:logout`) so translations can
  change without orphaning history.
- The palette will grow a server-backed search group in later specs; keep the
  command list derived from data (nav builder, actions array) rather than a
  bespoke registry.
- Decide whether ⌘K should also work on unauthenticated/auth pages (not for
  now — mounted from the authenticated app layout only).
