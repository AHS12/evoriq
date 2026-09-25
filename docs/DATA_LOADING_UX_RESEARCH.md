# Data Loading & UX Research — High-Standard Loading Behavior

> Research findings and effort estimate for upgrading data-loading UX
> (debounced search, skeletons, stale-while-revalidate) in this
> Laravel + Inertia 3 + React 19 starter.

---

## 1. TL;DR — The verdict

**The "TanStack Query-like behavior" you want is ~90% already built into Inertia 3 — the rework is adoption, not architecture.**

- **Debounced search**: already done. `use-data-table-filters.ts` debounces 300 ms and syncs to the URL with `preserveState`/`preserveScroll`/`replace`.
- **Skeleton loading**: the machinery exists but is **dormant** — `DataTable` accepts an `isLoading` prop and renders full skeleton rows, yet **zero pages pass it**.
- **"Old data shows until new data fetched"**: Inertia's partial reloads already do this (props only swap when the response arrives), and the `<Deferred>` component exposes a `reloading` flag designed exactly for "dim/indicate the old data while refreshing".
- **Client-side cache (stale-while-revalidate)**: Inertia has a built-in prefetch cache with `cacheFor={['30s','1m']}` semantics — fresh / stale / expired — identical in spirit to TanStack Query's `staleTime`/`cacheTime`.
- **TanStack Query**: **not needed as a global rework.** Adding it means bypassing Inertia's server-driven data flow for JSON endpoints — a parallel data layer to maintain. There are a few narrow cases where a hybrid is justified (see §6).

**Rework scale: mechanical, page-by-page, low risk.** The codebase has a single choke point (`use-data-table-filters.ts`) and a clean controller pattern (FilterDTO → Service → Resource), so most changes are small diffs per page rather than structural change.

---

## 2. Current state audit

### 2.1 What already exists (high reuse value)

| Capability | Status | Location |
|---|---|---|
| Debounced (300 ms), URL-synced filter engine with `only` support | ✅ Built | `hooks/use-data-table-filters.ts` |
| `DataTable` skeleton-row rendering | ✅ Built, **dormant** (no page passes `isLoading`) | `components/app/data-table/data-table.tsx:91` |
| `Spinner` + `form.processing` conventions in dialogs/forms | ✅ Consistent everywhere | ~20 components |
| Partial reloads (`only`) | ⚠️ Used on 2 of ~6 list pages | `notifications/index.tsx`, `data-processing/index.tsx` |
| `usePoll` for live-updating views | ✅ Built | `use-notification-poll.ts`, `use-job-poll.ts` |
| `<Link prefetch>` on navigation | ✅ Used | sidebar/header nav links |
| Global Inertia progress bar | ✅ Configured | `app.tsx:41` |
| Clean server side: FilterDTOs, Resources, `Inertia::render` | ✅ Consistent | e.g. `User/UserController.php` |

### 2.2 The gaps

1. **No per-table loading indication.** Filter/sort/page changes snap with zero feedback beyond the top progress bar. The skeleton path in `DataTable` was never wired to a data source.
2. **Most list pages re-fetch *all* props on every filter change.** `users/index.tsx`, `roles/index.tsx`, `audit-logs/index.tsx`, `files/index.tsx` have no `only: [...]` — each debounced keystroke re-evaluates `roles`, `statuses`, `processingOptions` etc. on the server and re-sends them.
3. **No deferred props / `whenVisible` / lazy evaluation** anywhere, client or server. Pages render only when their heaviest prop is ready.
4. **No prefetch cache for list navigation.** Going back to a list re-fetches fully; prefetch exists only on sidebar links.
5. **Duplicated debounce logic** in `files/index.tsx` (manual `setTimeout` instead of the shared hook).
6. **No pagination loading state** — prev/next buttons don't disable while the page request is in flight.

---

## 3. Key insight: Inertia 3 already ships the behaviors you described

### 3.1 "Old data shows until new data fetched" — partial reloads

When you call `router.get()` with `only: ['users']`, Inertia keeps the current page (with its old props) fully rendered and **swaps in the new props only when the response arrives**. This is functionally "keep previous data while fetching" (`keepPreviousData`-like) — built into the data layer, not the component tree.

The missing piece is only the *visual signal* that a reload is in flight (skeleton/opacity) — which the `Deferred` component (3.2) or a loading hook (Phase 1) provides.

> Docs: [Partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads)

### 3.2 Skeletons + reloading indicator — `<Deferred>`

Server side:

```php
'users' => Inertia::defer(fn () => UserResource::collection(
    $this->users->paginate(UserFilterDTO::fromRequest($request))
)),
```

Client side:

```tsx
<Deferred data="users" fallback={<DataTableSkeleton />}>
    {({ reloading }) => (
        <div className={reloading ? 'opacity-60' : ''}>
            <DataTable ... />
        </div>
    )}
</Deferred>
```

- `fallback` → rendered **on first load** (skeleton while the page shell paints immediately).
- `reloading` → `true` while a partial reload re-fetches the deferred key — the canonical **"old data stays visible, dimmed, until fresh data lands"** pattern, straight from the docs.
- Deferred props can be **grouped** (parallel requests) and combined with `once()` (resolve once, remembered across navigations — perfect for static option props like `roles`/`statuses`).
- Errors get a first-class `rescue` fallback with a retry button.

> Docs: [Deferred props](https://inertiajs.com/docs/v3/data-props/deferred-props)

### 3.3 Client-side cache — prefetching with stale-while-revalidate

```tsx
<Link href="/users" prefetch cacheFor={['30s', '1m']}>Users</Link>
```

- Fresh (< 30 s): served from cache, no request.
- Stale (30 s–1 m): **cached data shown instantly, refreshed in the background** — identical semantics to TanStack Query's `staleTime`/`gcTime`.
- Expired: normal navigation.

Also available: `router.prefetch(url, { data: { page: 2 } })` for programmatic prefetch (e.g. prefetch "next page" of a table on hover), `cacheTags` for grouped invalidation, and `invalidateCacheTags` on any `useForm` submission:

```ts
form.post('/users', { invalidateCacheTags: ['users'] });
```

> Docs: [Prefetching](https://inertiajs.com/docs/v3/data-props/prefetching)

### 3.4 Other relevant built-ins

| Feature | Use case here |
|---|---|
| `whenVisible` | Load a prop only when its container scrolls into view (heavy widgets, chart panels) |
| Lazy props (closures) | Only evaluate optional props when a partial reload actually asks for them — pairs with `only` |
| `once()` props | `roles`, `statuses`, `processingOptions` — resolve once, cached across navigations |
| `useRemember` | Persist local filter UI state across navigations if needed |

---

## 4. Feature mapping: desired UX → implementation

| Desired UX | Inertia-native answer | New code? |
|---|---|---|
| Debounced search | Already built (`use-data-table-filters`) | None (dedupe `files/index.tsx`) |
| Skeleton on first page load | `Inertia::defer` + `<Deferred fallback>` | Small per page |
| Old data visible while re-filtering | Partial reload (`only`) + `reloading` flag | Small per page |
| Old data visible while re-fetching (TanStack-like) | Same as above — props swap only on arrival | Built-in |
| Back-navigation instant, cached | `cacheFor` prefetch + `once()` props | Tiny |
| Prefetch next table page | `router.prefetch(..., { data: { page: page+1 } })` on hover of next button | Small |
| Cache invalidation after create/update/delete | `invalidateCacheTags` in existing `useForm` calls | Tiny |
| Loading indication on table | Wire `isLoading` into `DataTable` (already supported) | Foundation work |
| Disable pagination during fetch | Loading state from the same foundation hook | Foundation work |
| Live-updating lists (already present) | `usePoll` + `only` | Already done |
| Optimistic UI on row actions | Not native; see §6 | Optional, larger |

---

## 5. Effort estimate — phased plan

Effort ratings: **S** (small, mechanical), **M** (medium, some design decisions), **L** (large — none required in this plan 🎉).

### Phase 1 — Foundation (the only structural work) — **M**

One-time work that every page then reuses:

1. **Loading signal hook**: extend `use-data-table-filters.ts` to expose `isLoading` (track `router.on('start'/'finish')` / partial-reload events keyed to table visits). Single choke point — one hook feeds every list page.
2. **Wire `DataTable isLoading`**: pass it in `data-table.tsx` consumers; also disable pagination buttons while in flight.
3. **Convention**: dim-and-keep (`opacity-60` on stale data) vs skeleton (first load) — document which to use where.
4. Clean up `files/index.tsx` to use the shared hook.

No backend changes needed for this phase.

### Phase 2 — Partial reloads on all list pages — **S per page**

For `users`, `roles`, `audit-logs`, `files` (notifications & data-processing already correct):

1. Pass `only: ['users', 'filters', ...]` from the filter hook call.
2. Wrap remaining props (options) in **closures** on the server so they're not evaluated (lazy evaluation — the controller already separates them cleanly).
3. Optionally mark static option props `Inertia::defer(...)->once()`.

Mechanical, page-by-page, low risk. `users/index.tsx` is the pilot (heaviest page, biggest win).

### Phase 3 — Deferred props + first-load skeletons — **S–M per page**

1. `Inertia::defer()` the heavy prop (`users`, `roles`, `jobs`, ...).
2. Wrap the table in `<Deferred fallback={<SkeletonTable/>}>` with the `reloading` dim treatment.
3. Add a `SkeletonTable` variant built on the existing dormant skeleton rows.

This is what makes first paint instant (page shell + toolbar render immediately).

### Phase 4 — Prefetch cache & invalidation — **S–M**

1. Extend `prefetch` usage beyond nav links to table pagination (`router.prefetch` next page on hover) where it makes sense.
2. Add `cacheFor={['30s','1m']}` (or project-appropriate values) to list links.
3. Tag caches (`cacheTags: ['users']`) and add `invalidateCacheTags` to existing mutation calls (create/update/delete dialogs).

Decide policy per page: activity/audit pages should probably **never** serve stale data; reference lists benefit most.

### Phase 5 (optional) — Advanced UX — **M each**

- `whenVisible` for below-the-fold heavy sections (e.g. stats cards on data-processing page).
- Optimistic row actions (instant delete/toggle with rollback) — requires custom logic on top of `useForm`; only worth it for high-frequency actions.
- Incremental/appending list loads (`mergeProps`) if an "infinite scroll" mode is ever wanted.

### Summary of rework scope

| Area | Nature | Risk |
|---|---|---|
| `use-data-table-filters.ts` + `DataTable` | One-time foundation | Low |
| 4 list pages: partial reloads | Mechanical diff per page | Low |
| 6 list pages: deferred + skeletons | Mechanical per page | Low |
| Prefetch/invalidation | Small per page, policy decision per page | Low (stale-data policy needs care) |
| TanStack Query adoption | **Not planned** | n/a |

**No architectural rework is required.** No new dependencies, no changes to the Service–Repository flow, no new data layer. The controller/FilterDTO/Resource pattern is already exactly what partial reloads, deferred props and lazy evaluation expect.

---

## 6. Where TanStack Query still makes sense (and why not by default)

**Arguments against a global adoption here:**

- The app is **server-driven via Inertia**: props come from controllers, authorization and validation are server-side, navigation is Inertia's. TanStack Query would need a parallel JSON API for the same data — two sources of truth, duplicated loading/invalidation logic, and it weakens the "controller sends typed props" contract this codebase follows.
- The behaviors motivating TQ adoption (stale-while-revalidate, keep-previous-data, caching, debounced refetch) are all covered natively by Inertia 3 features (§3).

**Legitimate hybrid cases (add TQ *only* for these, if ever):**

1. **Client-side "pure" resources** not tied to a page render — e.g. a global command palette that searches across entities via JSON endpoints, autocomplete widgets, cross-page widgets whose lifecycle doesn't match navigation.
2. **Optimistic mutations with rich rollback** — TQ's mutation queue is more ergonomic than hand-rolling on `useForm`.
3. **Heavy polling with cache sharing** between multiple components viewing the same data — though `usePoll` + `only` already covers the current notification/job-center needs.

If adopted later, the clean pattern is: keep Inertia for pages/navigation, use TQ with Wayfinder-generated URLs for isolated client-only widgets — never for the list pages themselves.

---

## 7. Risks & gotchas to watch

- **Flash messages & partial reloads**: partial reloads only fetch requested props — verify toasts (flashed via `Inertia::flash('toast', ...)`) aren't dropped on `only` visits. (notifications/data-processing pages already handle this; keep their approach as the reference.)
- **Stale option props**: `roles`/`statuses` filters sent back on every partial reload could go stale after another tab modifies them — acceptable tradeoff; `once()` makes it explicit.
- **Cursor pagination** (audit-logs): partial reloads work, but prefetch caching of cursor URLs is less predictable than `page=` — test before enabling prefetch there.
- **`preserveState` interplay**: with `Deferred`, a partial reload keeps state; confirm `useForm`-backed dialogs stay mounted/clean.
- **Zod schemas & prop types**: when props become deferred they can be `undefined` on first render — type the props accordingly (`Paginated<T> | undefined`) and let `<Deferred>` handle rendering.

---

## 8. Recommended order of execution

1. Phase 1 (foundation) — everything else depends on the loading signal.
2. Pilot on `users/index.tsx` end-to-end (Phase 2 + 3) — it's the heaviest page and validates the whole pattern.
3. Roll out to remaining list pages (mechanical repetition of the pilot).
4. Phase 4 (prefetch/invalidation) once the rollout is stable.
5. Phase 5 items only where a real need appears.

## Sources

- [Inertia — Partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads)
- [Inertia — Deferred props](https://inertiajs.com/docs/v3/data-props/deferred-props)
- [Inertia — Prefetching](https://inertiajs.com/docs/v3/data-props/prefetching)
- [Inertia — Once props](https://inertiajs.com/docs/v3/data-props/once-props)
- [Practical guide to deferred props](https://dev.to/akramghaleb/a-practical-guide-to-deferred-props-in-inertia-2-da5)
