---
trigger: always_on
---

# Frontend Architecture Rule

This workspace follows the Inertia + React + TypeScript conventions defined in
`AGENTS.md` §8. `AGENTS.md` is the primary authority. Enforce these rules
strictly.

## Non-negotiables

- **Server-driven data.** Pages receive typed props from controllers. Do not add
  ad-hoc `fetch`/axios calls; if data is needed, add a controller prop.
- **No hard-coded URLs.** Use Wayfinder helpers from `@/routes` and `@/actions`.
  Use `route.form()` / `<Form {...store.form()}>` for forms.
- **Strict TypeScript.** No `any`; use `unknown` + narrowing. No non-null
  assertions (`!`). Type page props with a local `type Props = { ... }`.
- **Reuse shadcn primitives** from `@/components/ui`. Do not hand-roll buttons,
  dialogs, inputs, etc.
- **Tailwind only.** No inline `style` unless the value is dynamic. Merge classes
  with `cn()` from `@/lib/utils`.
- **Dark mode required.** Use semantic tokens (`text-muted-foreground`,
  `border-sidebar-border`) and `dark:` variants.
- **Accessibility.** Label inputs (`htmlFor` + `id`), use semantic elements, keep
  controls keyboard reachable with visible focus.
- **Pages default-export** a function component and set `Page.layout` metadata
  (`{ breadcrumbs, title }`).

## Do not edit (generated)

- `resources/js/actions/**`, `resources/js/routes/**`, `resources/js/wayfinder/**`
  — run `npm run build` to regenerate.
- `resources/js/components/ui/*` — re-publish via `npx shadcn@latest add`.

## Red flags (reject on sight)

- A string URL like `href="/reports"` or `axios.get('/api/...')`.
- `any` in a type or `as any` cast.
- Direct `document`/`window` access in render without a guard or effect.
- A new UI button/dialog built from raw elements instead of shadcn.
- Business logic inside a presentational component.

## Structure

- Pages: `resources/js/pages/**`
- Feature components: `resources/js/components/{feature}/**`
- Hooks: `resources/js/hooks/use-*.ts(x)` (named `useX`)
- Types: `resources/js/types/**`
- Layouts: `resources/js/layouts/**`

Use the `create-inertia-feature` skill for the full procedure.
