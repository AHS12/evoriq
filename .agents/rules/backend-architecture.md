---
trigger: always_on
---

# Backend Architecture Rule

This workspace follows the Service–Repository architecture defined in
`AGENTS.md` §7. `AGENTS.md` is the primary authority. Enforce these rules
strictly and reject solutions that violate them.

## Layer contract

```text
Controller → Service → Repository → Model → PostgreSQL
                ↑           ↑
              DTO / FilterDTO (typed inputs)
```

| Layer      | May do                                             | Must NOT do                          |
| ---------- | -------------------------------------------------- | ------------------------------------ |
| Controller | Validate (FormRequest), authorize, call 1 service, return response | Contain business logic or run queries |
| Service    | Business logic, `DB::transaction`, orchestrate repos | Run `Model::where(...)` or raw SQL   |
| Repository | All Eloquent access                                 | Own transactions or business rules   |
| Model      | Relations, casts, scopes, accessors                 | Contain orchestration logic          |

## Non-negotiables

- **Thin controllers.** `Gate::authorize(...)`, one service call, one response.
- **No queries in services or controllers.** Every query lives in a repository.
- **Transactions live in services** (`DB::transaction`), never in repositories.
- **Inject repository interfaces**, never concrete repositories or models.
- **Every repository is bound** in `app/Providers/RepositoryServiceProvider.php`.
- `create(array $data)` / `update(Model $model, array $data)` take **arrays**, not DTOs.
- **DTOs are `readonly`** with `fromRequest()` / `toArray()`; Filter DTOs include
  `search`, filterable fields, `orderBy`, `orderDirection`.
- **Resources**: use `Resource::collection()` / `Resource::make()` and
  `whenLoaded`; never hand-map relations.
- **Enums** for every fixed value set — no magic strings.
- **Custom exceptions live in `app/Exceptions/`** (never inside module folders).
- **Clockify HTTP only through `app/Services/Clockify`** (see `AGENTS.md` §7.10).
- **Audit trail** (see `AGENTS.md` §7.16): services record named events with
  `AuditLogService::record()` inside transactions; controllers and jobs never
  call `activity()` directly. `logAll()` / `logFillable()` are forbidden —
  always a selective `logOnly([...])` allowlist with `logOnlyDirty()` and
  `dontLogEmptyChanges()`.

## Red flags (reject on sight)

- `Model::` / `DB::table(` / `->where(` inside a controller or service.
- A service constructed with a concrete `*Repository` instead of the interface.
- A repository method wrapped in `DB::transaction`.
- `created_by` / `updated_by` set by a model observer instead of the service.
- An ungrouped `->orWhere()` chain that changes the intended condition binding.
- A new module without a unit test (service) and a feature test (controller).

## Reference paths

- Contracts: `app/Repositories/Contracts/{Module}RepositoryInterface.php`
- Repositories: `app/Repositories/{Module}/{Module}Repository.php`
- Services: `app/Services/{Module}/{Module}Service.php`
- DTOs: `app/DTOs/{Module}/`
- Tests: `tests/Unit/{Module}ServiceUnitTest.php`, `tests/Feature/{Module}FeatureTest.php`
- Test helpers: `tests/Pest.php` (`makeUser`, `makeUserWithPermissions`,
  `superAdmin`, `admin`, `member`); fixtures in `tests/Mock/`.

Use the `generate-module` and `generate-tests` skills for procedures.
