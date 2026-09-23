---
name: generate-module
description: 'Scaffold a Laravel module (migration, model, factory, enum, DTO + FilterDTO, repository + interface, service, request, resource, controller, policy, routes, tests) following Evoriq''s Service–Repository architecture. Use when adding a new entity/module to the backend.'
argument-hint: 'Entity name and key fields, for example: Report with name, type, range_start, range_end.'
---

# Generate Laravel Module

## When to use

Use when adding a new backend entity/module with CRUD or list behavior, or any
feature that needs persistence (e.g. `Connection`, `Report`, `Export`,
`SyncRun`). Read `AGENTS.md` §7 and `.agents/rules/backend-architecture.md`
first.

> There is no CRUD generator command in Evoriq. Create files by hand following
> the checklist below **exactly** — do not deviate from these conventions.

## 1. Preparation

1. **Entity name** (singular PascalCase, e.g. `Report`) and table name
   (snake_case plural, e.g. `reports`).
2. **Fields** with types, nullability, defaults, uniqueness, indexes.
3. **Relations** (belongsTo/hasMany) and any ownership columns (e.g. `user_id`).
4. **Features**: filtering/search, enum states, nested children, jobs.

Decision points:
- Fixed value sets → create an `app/Enums/{Name}.php` first and plan casts.
- List endpoints need search/filter/sort → define a `FilterDTO` and a
  `buildFilterQuery()`.
- Nested children → plan granular repository methods + service delete–insert.
- Ownership → derive it from `auth()->id()` in the service; never accept it
  from the client.

## 2. Migration, Model, Factory, Enum

- **Migration** `database/migrations/*_create_{table}_table.php`: columns, indexes
  for filterable/foreign columns, and audit columns
  `$table->foreignId('created_by')->nullable();` / `updated_by`.
- **Enum** `app/Enums/{Name}.php` for fixed values; cast it on the model.
- **Model** `app/Models/{Model}.php`: `$fillable`, `$casts`, typed relations,
  scopes returning `Builder`, and `creator()` / `updater()` `BelongsTo`.
- **Factory** `database/factories/{Model}Factory.php`; add `with{Child}()` helpers
  using `afterCreating` for nested data. Import classes (no FQCN in body).

## 3. DTOs

`app/DTOs/{Module}/{Module}DTO.php` — `readonly`, typed promoted properties,
`fromRequest()` using `$request->validated()`, and `toArray()`.

`app/DTOs/{Module}/{Module}FilterDTO.php` — `readonly`; `?string $search`,
nullable filterable fields, `orderBy` / `orderDirection` defaults. Build from
`$request->input(...)` (no dedicated index FormRequest).

```php
final readonly class ReportDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}

    public static function fromRequest(StoreReportRequest $request): self
    {
        return new self(...$request->validated());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
```

## 4. Repository (interface + implementation)

`app/Repositories/Contracts/{Module}RepositoryInterface.php`:

```php
interface ReportRepositoryInterface
{
    public function paginate(ReportFilterDTO $filters, int $perPage = 15, array $relations = []): LengthAwarePaginator;

    public function findById(int|string $id, array $relations = []): ?Report;

    /** @param array<string, mixed> $data */
    public function create(array $data): Report;

    /** @param array<string, mixed> $data */
    public function update(Report $report, array $data): Report;

    public function delete(Report $report): bool;
}
```

`app/Repositories/{Module}/{Module}Repository.php`:
- Implement `buildFilterQuery(ReportFilterDTO $filters, array $relations = []): Builder`.
- `paginate()` uses `buildFilterQuery()`.
- `findById()` uses `Model::with($relations)->find($id)`.
- **No transactions here.**

Bind the interface in `app/Providers/RepositoryServiceProvider.php`:

```php
public array $bindings = [
    ReportRepositoryInterface::class => ReportRepository::class,
];
```

## 5. Service

`app/Services/{Module}/{Module}Service.php` — inject the **interface**. Wrap all
writes in `DB::transaction`. No queries.

```php
public function create(ReportDTO $dto): Report
{
    return DB::transaction(fn (): Report => $this->reports->create($dto->toArray()));
}
```

## 6. Request, Resource, Policy

- **Request** `app/Http/Requests/{Module}/Store{Module}Request.php` (and `Update…`):
  pipe-separated rules; `Rule::enum(...)` for enums.
- **Resource** `app/Http/Resources/{Module}/{Module}Resource.php`: inherit with
  `...parent::toArray($request)`, append relations with `whenLoaded`, keep nested
  resources slim.
- **Policy** `app/Policies/{Model}Policy.php` + `Gate::authorize(...)` in every
  controller action.

## 7. Controller

`app/Http/Controllers/{Module}/{Module}Controller.php` — thin; no logic/queries.
Return an Inertia page for UI, or a `Resource` for JSON endpoints.

```php
public function index(Request $request, ReportService $service)
{
    Gate::authorize('viewAny', Report::class);

    return Inertia::render('reports/index', [
        'reports' => ReportResource::collection(
            $service->paginate(ReportFilterDTO::fromRequest($request))
        ),
    ]);
}

public function store(StoreReportRequest $request, ReportService $service)
{
    Gate::authorize('create', Report::class);

    return to_route('reports.show', $service->create(ReportDTO::fromRequest($request)));
}
```

## 8. Routes

Add the resource in `routes/web.php` (Inertia pages) or `routes/api.php` (JSON),
grouped with the `auth` middleware. Run `npm run build` afterwards so Wayfinder
regenerates helpers.

## 9. Tests

Add both, following the `generate-tests` skill:
- `tests/Unit/{Module}ServiceUnitTest.php` — mock the repository interface.
- `tests/Feature/{Module}FeatureTest.php` — `RefreshDatabase`, assert response +
  database state.

## 10. Completion checks

- [ ] Interface bound in `RepositoryServiceProvider`.
- [ ] Every query is in the repository; every write is in a service transaction.
- [ ] Policy + `Gate::authorize` on all controller actions.
- [ ] Resource uses nested resources, no manual relation mapping.
- [ ] Ownership derived from `auth()->id()` where applicable.
- [ ] Unit + feature tests exist and pass.
- [ ] `composer check` passes.
