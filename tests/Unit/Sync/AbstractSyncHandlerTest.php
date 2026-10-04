<?php

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Exceptions\ApiException;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Models\Concerns\BelongsToOrganization;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use App\Services\Sync\SyncJobRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Schema::dropIfExists('abstract_sync_test_entities');

    Schema::create('abstract_sync_test_entities', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('organization_id')->nullable();
        $table->unsignedBigInteger('workspace_id')->nullable();
        $table->string('clockify_id');
        $table->string('name')->nullable();
        $table->timestamp('synced_at')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();

        $table->unique(['organization_id', 'workspace_id', 'clockify_id']);
    });

    config(['clockify.retry.sleep' => 0]);
});

/**
 * @return array{0: SyncContext, 1: ClockifyWorkspace}
 */
function makeHandlerScope(): array
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    return [new SyncContext($connection, $workspace, pageSize: 100), $workspace];
}

function makeHandlerJob(ClockifyWorkspace $workspace, array $attributes = []): ClockifySyncJob
{
    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $workspace->connection_id,
        'workspace_id' => $workspace->id,
    ]);

    return ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'entity_type' => SyncEntityType::CLIENTS,
        'phase' => SyncPhase::REFERENCE,
        ...$attributes,
    ]);
}

function fakeClientHandler(): FakeClientSyncHandler
{
    return new FakeClientSyncHandler(app(ClockifyClient::class));
}

test('fetchPage requests the entity endpoint with page parameters', function () {
    [$context, $workspace] = makeHandlerScope();

    Http::fake([
        '*' => Http::response([
            ['id' => 'c1', 'name' => 'A'],
            ['id' => 'c2', 'name' => 'B'],
        ], 200),
    ]);

    $items = fakeClientHandler()->fetchPage($context, 3);

    expect($items)->toHaveCount(2);

    Http::assertSent(fn ($request): bool => str_contains(
        $request->url(),
        "/workspaces/{$workspace->clockify_id}/clients",
    )
        && (int) $request['page'] === 3
        && (int) $request['page-size'] === 100);
});

test('fetchPage wraps a single-object response', function () {
    [$context] = makeHandlerScope();

    Http::fake(['*' => Http::response(['id' => 'c1', 'name' => 'A'], 200)]);

    expect(fakeClientHandler()->fetchPage($context, 1))
        ->toBe([['id' => 'c1', 'name' => 'A']]);
});

test('fetchPage throws when Clockify returns an error', function () {
    [$context] = makeHandlerScope();

    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    expect(fn () => fakeClientHandler()->fetchPage($context, 1))
        ->toThrow(ApiException::class);
});

test('map delegates to the handler mapping', function () {
    [$context] = makeHandlerScope();

    expect(fakeClientHandler()->map(['id' => 'c1', 'name' => 'A'], $context))
        ->toBe(['clockify_id' => 'c1', 'name' => 'A']);
});

test('delete soft-deletes the entity by default', function () {
    [$context, $workspace] = makeHandlerScope();

    $entity = AbstractSyncTestEntity::query()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'c1',
        'name' => 'A',
    ]);

    fakeClientHandler()->delete($context, 'c1');

    expect($entity->refresh()->deleted_at)->not->toBeNull();
});

test('delete removes the row when the policy is replace', function () {
    [$context, $workspace] = makeHandlerScope();

    AbstractSyncTestEntity::query()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'c1',
        'name' => 'A',
    ]);

    (new ReplaceClientSyncHandler(app(ClockifyClient::class)))->delete($context, 'c1');

    expect(AbstractSyncTestEntity::query()->count())->toBe(0);
});

test('delete is a no-op when the policy is none', function () {
    [$context, $workspace] = makeHandlerScope();

    AbstractSyncTestEntity::query()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'c1',
        'name' => 'A',
    ]);

    (new NoneClientSyncHandler(app(ClockifyClient::class)))->delete($context, 'c1');

    expect(AbstractSyncTestEntity::query()->count())->toBe(1);
});

test('the runner fetches, stores and upserts every page', function () {
    [, $workspace] = makeHandlerScope();
    $job = makeHandlerJob($workspace, ['page_size' => 2]);

    app(SyncHandlerRegistry::class)->register(SyncEntityType::CLIENTS, FakeClientSyncHandler::class);

    Http::fake(fn ($request) => (int) ($request['page'] ?? 1) === 1
        ? Http::response([['id' => 'c1', 'name' => 'A'], ['id' => 'c2', 'name' => 'B']], 200)
        : Http::response([['id' => 'c3', 'name' => 'C']], 200));

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect(AbstractSyncTestEntity::query()->count())->toBe(3)
        ->and($job->records_processed)->toBe(3)
        ->and($job->records_created)->toBe(3)
        ->and($job->page)->toBe(2);
});

test('a malformed row is skipped without failing the page', function () {
    [, $workspace] = makeHandlerScope();
    $job = makeHandlerJob($workspace, ['page_size' => 10]);

    app(SyncHandlerRegistry::class)->register(SyncEntityType::CLIENTS, FakeClientSyncHandler::class);

    Http::fake(['*' => Http::response([
        ['id' => 'c1', 'name' => 'A'],
        ['name' => 'missing id'],
        ['id' => 'c3', 'name' => 'C'],
    ], 200)]);

    Log::spy();

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect(AbstractSyncTestEntity::query()->count())->toBe(2)
        ->and($job->records_created)->toBe(2);

    Log::shouldHaveReceived('warning')->once();
});

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string|null $name
 */
class AbstractSyncTestEntity extends Model
{
    use BelongsToOrganization;

    protected $table = 'abstract_sync_test_entities';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}

class AbstractSyncTestRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return AbstractSyncTestEntity::class;
    }
}

class FakeClientSyncHandler extends AbstractSyncHandler
{
    public function entityType(): SyncEntityType
    {
        return SyncEntityType::CLIENTS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return AbstractSyncTestRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/clients";
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        if (! isset($raw['id'])) {
            throw new InvalidArgumentException('The row is missing its id.');
        }

        return [
            'clockify_id' => (string) $raw['id'],
            'name' => $raw['name'] ?? null,
        ];
    }
}

class ReplaceClientSyncHandler extends FakeClientSyncHandler
{
    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::REPLACE;
    }
}

class NoneClientSyncHandler extends FakeClientSyncHandler
{
    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::NONE;
    }
}
