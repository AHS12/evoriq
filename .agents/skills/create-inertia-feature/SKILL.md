---
name: create-inertia-feature
description: 'Build a typed React/Inertia feature for Evoriq: page, feature components, types, Wayfinder navigation, forms and styling following the frontend best practices.'
argument-hint: 'Feature name and purpose, for example: Reports index with filters and a create form.'
---

# Create an Inertia Feature

## When to use

Use when adding a new page or UI feature to the React/Inertia frontend. Read
`AGENTS.md` §8 and `.agents/rules/frontend-architecture.md` first.

## Golden rules

- Data is **server-driven**: the controller sends typed props. No ad-hoc fetching.
- Navigate with **Wayfinder** (`@/routes`, `@/actions`) — never hard-code URLs.
- **Strict TypeScript**: no `any`, type the props, `import type` for types.
- Reuse **shadcn/ui** primitives from `@/components/ui`.
- **Tailwind** utilities only; merge with `cn()`; always include `dark:` styles.
- Pages default-export and set `Page.layout` metadata.

## 1. Backend first

Add the controller action that renders the page and the route. Then run
`npm run build` so Wayfinder generates the helpers you will import.

```php
// app/Http/Controllers/Report/ReportController.php
public function index(Request $request, ReportService $service)
{
    Gate::authorize('viewAny', Report::class);

    return Inertia::render('reports/index', [
        'reports' => ReportResource::collection($service->paginate(ReportFilterDTO::fromRequest($request))),
    ]);
}
```

## 2. Types

Put shared shapes in `resources/js/types`; keep page-local props inline.

```ts
// resources/js/types/report.ts
export type Report = {
    id: number;
    name: string;
    created_at: string;
};
```

## 3. Page

`resources/js/pages/reports/index.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index, create } from '@/routes/reports';
import type { Report } from '@/types/report';

type Props = {
    reports: {
        data: Report[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function ReportsIndex({ reports }: Props) {
    return (
        <>
            <Head title="Reports" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Reports</h1>
                    <Button asChild>
                        <Link href={create()}>New report</Link>
                    </Button>
                </div>

                <Card className="divide-y">
                    {reports.data.map((report) => (
                        <div key={report.id} className="p-4">
                            {report.name}
                        </div>
                    ))}
                </Card>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
    title: 'Reports',
};
```

## 4. Forms

Use Inertia's `<Form>` (or `useForm`) with the Wayfinder `.form()` payload.
Render validation errors with `<InputError />` and disable while processing.

```tsx
import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/reports';

export default function ReportCreate() {
    return (
        <Form {...store.form()} className="flex max-w-md flex-col gap-4 p-4">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" name="name" required />
                        <InputError message={errors.name} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Save
                    </Button>
                </>
            )}
        </Form>
    );
}
```

## 5. Components

- Extract reusable UI into `resources/js/components/{feature}/`.
- Shared components use **named exports**; pages use **default exports**.
- Keep components presentational; no business logic.
- Reuse shadcn primitives — do not rebuild them.

## 6. Quality checklist

- [ ] Props are typed; no `any`; `import type` used.
- [ ] All links/forms use Wayfinder helpers (no hard-coded URLs).
- [ ] `<Head title>` set and `Page.layout` metadata assigned.
- [ ] Uses shadcn primitives; classes merged with `cn()` where conditional.
- [ ] Dark-mode styles provided; semantic tokens used.
- [ ] Inputs have `Label htmlFor` + matching `id`; controls keyboard reachable.
- [ ] Page/controller feature test added (see `generate-tests`).
- [ ] `npm run build`, `npm run check`, and `composer check` pass.
