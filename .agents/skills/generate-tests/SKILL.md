---
name: generate-tests
description: 'Generate Pest unit tests (service layer with Mockery) and feature tests (controllers/pages) for an Evoriq module, following the project''s 1:1 testing mapping.'
argument-hint: 'Module name, for example: Report.'
---

# Generate Tests

## When to use

After creating or changing a module, or when a module lacks tests. Strict 1:1
mapping is required (`AGENTS.md` §7.12):

- **1 Service = 1 unit test** → `tests/Unit/{Module}ServiceUnitTest.php`
- **1 Controller = 1 feature test** → `tests/Feature/{Module}FeatureTest.php`

Feature tests run against the `:memory:` SQLite database configured in
`phpunit.xml` and use `RefreshDatabase`.

## 1. Unit tests (service layer)

Mock the **repository interface** and assert the service calls it with the right
arguments. Facades used by the service (`DB`, `Gate`, `Auth`) are mocked too.

```php
<?php

use App\DTOs\Report\ReportDTO;
use App\DTOs\Report\ReportFilterDTO;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Report\ReportService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery;

beforeEach(function () {
    DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

    $this->reports = Mockery::mock(ReportRepositoryInterface::class);
    $this->service = new ReportService($this->reports);
});

test('paginate calls the repository with filters', function () {
    $filters = new ReportFilterDTO(search: 'q3');
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->reports->shouldReceive('paginate')
        ->once()
        ->with($filters, 15, [])
        ->andReturn($paginator);

    expect($this->service->paginate($filters))->toBe($paginator);
});

test('create calls the repository inside a transaction', function () {
    $dto = new ReportDTO(name: 'Q3 2026');

    $this->reports->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data) => $data['name'] === 'Q3 2026'))
        ->andReturn(new Report(['name' => 'Q3 2026']));

    $this->service->create($dto);
});
```

## 2. Feature tests (controller / page)

Use factories for state, `RefreshDatabase`, and assert both the response and the
database. Inertia pages assert the component + props; JSON endpoints assert
status + JSON structure.

```php
<?php

use App\Models\Report;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can list reports', function () {
    Report::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('reports.data', 3));
});

test('can create a report', function () {
    $this->actingAs($this->user)
        ->post(route('reports.store'), ['name' => 'Q3 2026'])
        ->assertRedirect();

    $this->assertDatabaseHas('reports', ['name' => 'Q3 2026']);
});

test('rejects invalid data', function () {
    $this->actingAs($this->user)
        ->post(route('reports.store'), [])
        ->assertSessionHasErrors('name');
});
```

> For JSON endpoints use `getJson` / `postJson` and assert `assertStatus`,
> `assertJsonStructure`, `assertJsonPath`.

## 3. Conventions

- Pest functions: `test('description', function () { ... });` (no `describe`).
- Mockery: `Mockery::mock()`, `shouldReceive()->once()->with(...)->andReturn(...)`.
- Unit assertions: `expect($result)->toBe(...)`.
- Feature assertions: `assertOk()`, `assertRedirect()`, `assertSessionHasErrors()`,
  `assertDatabaseHas()`.
- Test domain exceptions with `->toThrow(...)`.
- Never hit the real Clockify API — `Http::fake()` (see the
  `clockify-sync-feature` skill).
- `tests/Unit` files must opt in with
  `uses(Tests\TestCase::class, RefreshDatabase::class);` (only the `Feature`
  suite gets these automatically).
- The base `tests/TestCase.php` seeds the database (`$seed = true`), so roles,
  permissions, the super admin and settings exist in every Feature test. Prefer
  the shared helpers from `tests/Pest.php`: `makeUser()`,
  `makeUserWithPermissions()`, `superAdmin()`, `admin()`, `member()`.
- Reuse fixtures from `tests/Mock/*` (e.g. `ExportMockData::request()`,
  `UploadMockData::imageFile()`) instead of duplicating payloads.

## 4. Completion checks

- [ ] One unit test file per service, one feature test file per controller.
- [ ] Happy path + at least one validation/authorization failure covered.
- [ ] No real external HTTP calls.
- [ ] `php artisan test` and `composer check` pass.
