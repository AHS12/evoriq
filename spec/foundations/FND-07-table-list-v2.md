# FND-07 — Table & list v2 (density, sticky, saved views)

- **Status:** Done
- **Epic:** foundations
- **Estimate:** M
- **Depends on:** FND-01, FND-03
- **Blocks:** DASH-*, REP-08, future list-heavy modules
- **TDR:** §30, §31, §35–38

## 1. Why

Every module lands its records in the same shared table (`DataTable`,
`DataTableToolbar`) — users, roles, audit logs, files, and soon projects,
clients, tasks and reports. Today the table has one fixed density, no sticky
header, no way to hide columns that do not matter to the reader, and no way to
keep a filter combination. Long tables scroll their headers away, wide tables
force horizontal scrolling past noise, and every session re-applies the same
filters by hand. Table & list v2 makes the shared table a first-class reading
surface: comfortable or compact rows, a header that stays put, per-user column
visibility and reusable saved views — persisted locally per table.

## 2. Scope

**In**

- **Density** — `comfortable` (default) and `compact`; applied to the shared
  table so row height and cell padding shrink/grow consistently.
- **Sticky header** — opt-in `stickyHeader` on `DataTable`; the header stays
  visible while the table body scrolls inside a bounded container.
- **Column visibility** — a "View" control listing hideable columns with
  checkboxes and a reset; columns with `enableHiding: false` (row actions,
  checkbox) are never listed.
- **Saved views** — name and store the current filter set per table; apply,
  rename and delete from a dropdown. Applying a view routes through the page's
  existing `apply()` so Inertia stays the source of truth.
- **Persistence** — a small versioned `localStorage` store keyed by a stable
  `tableId`; preference reads/writes are pure and unit-tested.
- **Adoption** — wire `tableId`, density, sticky header, view options and saved
  views into the existing `DataTable` list pages: users, roles and audit logs.

**Out**

- Server-side persistence of preferences/saved views (later: `UserSettingKey`
  or a dedicated model). This spec is local-first.
- Grouping, row reordering, column resizing/reordering, virtualization.
- Filter builder UI beyond what each page already exposes (REP-01 owns that).
- Backend changes of any kind.

## 3. Data model

None (client-only). Stored shape in `localStorage` under
`evoriq.table.<tableId>.v1`:

```ts
type TableDensity = 'comfortable' | 'compact';

type TableSavedView = {
    id: string;
    name: string;
    filters: Record<string, string | number | boolean | null>;
    createdAt: string;
    isDefault?: boolean;
};

type TablePreferenceStore = {
    version: 1;
    density: TableDensity;
    columnVisibility: Record<string, boolean>;
    stickyHeader: boolean;
    views: TableSavedView[];
};
```

Unknown/missing keys fall back to defaults; malformed JSON is discarded.

## 4. Backend

None. Saved views serialize the existing query params, which each page's
server already validates.

## 5. Frontend / UI

**Files**

- `lib/table-preferences.ts` — pure, storage-injected read/write/merge plus
  `defaultTablePreferences()`; version guard and safe JSON parsing.
- `hooks/use-table-preferences.ts` — `useTablePreferences(tableId)` returning
  density, sticky, column visibility and saved views with setters and CRUD;
  hydrates in an effect (SSR-safe) and persists on change.
- `components/app/data-table/data-table-density-toggle.tsx` — toggle group
  (`comfortable` / `compact`).
- `components/app/data-table/data-table-view-options.tsx` — dropdown of
  hideable columns + reset.
- `components/app/data-table/data-table-saved-views.tsx` — dropdown to save,
  apply, rename and delete views.
- `components/app/data-table/data-table-view-controls.tsx` — composes density +
  view options (+ saved views when filters exist) for the toolbar.
- `components/app/data-table/data-table.tsx` — gains `density`,
  `columnVisibility`, `onColumnVisibilityChange` and `stickyHeader` props, and
  threads `columnVisibility` into TanStack state.
- `hooks/use-data-table-filters.ts` — no API change; saved views call `apply`.

**Experience**

- The toolbar gains a right-aligned controls cluster: saved views (when the
  page has filters), then density and View. Order stays stable across pages.
- Selecting Compact immediately tightens rows; the choice persists for the
  table and survives reload and navigation.
- The View menu lists friendly column names as checkboxes; unchecking hides the
  column instantly and persists. Actions/selection columns are not listed.
- Saving a view opens a small dialog for a name (default from the current
  filters); the view appears in the dropdown and applies with a single click.
- Sticky header keeps the header row pinned with a background so scrolled rows
  never show through.
- Reduced motion / no layout surprises: density changes are instant (no
  animation), per FND-01.

**States**

- No saved views → dropdown shows an `EmptyState variant="compact"`-style
  prompt, not an empty menu.
- Local storage unavailable (private mode) → preferences degrade to
  in-memory defaults for the session; nothing throws.

### A11y & i18n

- Density toggle and View control are keyboard-reachable buttons with
  `aria-label`s; the View menu uses `role="menu"` checkbox items with
  `aria-checked`.
- Sticky header keeps the semantic `<thead>/<th>` structure; no change to table
  roles.
- Hidden columns are removed via TanStack visibility (not CSS), so screen
  readers only encounter visible data.
- All new strings (`Density`, `Comfortable`, `Compact`, `View`, `Toggle
  columns`, `Reset`, `Saved views`, `Save view`, `View name`, `Apply view`,
  `Rename view`, `Delete view`, `No saved views yet`, `Sticky header`, …) are
  added to all five `lang/app/*.json` dictionaries.

## 6. API / routes / props

No new endpoints. `DataTable` prop additions are additive and optional, so
existing call sites keep working unchanged.

## 7. Acceptance criteria

- [x] `DataTable` supports `density`, `columnVisibility` and `stickyHeader`
      without breaking existing pages.
- [x] Density toggles apply and persist per table across reloads.
- [x] Sticky header keeps the header visible while the body scrolls.
- [x] View options hide/show columns; non-hideable columns are excluded; the
      selection persists per table.
- [x] Saved views can be created, applied, renamed and deleted; applying one
      updates the URL through the page's `apply()`.
- [x] Preferences are per `tableId`; unrelated tables are unaffected.
- [x] Malformed/absent storage never throws and falls back to defaults.
- [x] Users, roles and audit logs pages adopt the controls.
- [x] Vitest coverage for the store, the hook, view options and saved views.
- [x] `composer check` passes.

## 8. Tests

- `lib/table-preferences.test.ts` — defaults, round-trip, version mismatch and
  malformed JSON, per-table isolation.
- `hooks/use-table-preferences.test.ts` — hydrate/persist density, sticky and
  column visibility; saved-view CRUD.
- `components/app/data-table/data-table-view-options.test.tsx` — renders
  hideable columns, toggles visibility, excludes non-hideable columns.
- `components/app/data-table/data-table-saved-views.test.tsx` — save applies and
  persists, apply invokes the callback, delete removes.
- `components/app/data-table/data-table.test.tsx` — density class + sticky
  header classes, empty/loading states unchanged.
- No backend tests (presentational/client-only). Inertia feature tests for the
  list pages already cover rendering.

## 9. Notes & open questions

- **Storage choice:** local-first keeps FND-07 to an M and avoids a migration.
  If saved views should follow the user across devices, promote them to a
  `UserSettingKey`/model later without changing the component API.
- **Sticky header** relies on the table's own scroll container; pages should
  not wrap the table in a second `overflow` container or sticky will bind to
  the wrong scrollport.
- **Column labels** come from each page's column definitions, so the View menu
  reuses the same translated `title`s.
- Decide whether density should also apply to the standalone `Table` usages
  (the files list view is a hand-rolled `Table`, not `DataTable`); for now the
  controls are wired to `DataTable` consumers (users, roles, audit logs).
- Saved views intentionally store filters only (not density/columns) so a view
  is portable across density preferences; revisit if users expect layout too.
