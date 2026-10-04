<?php

use App\DTOs\Sync\EntityChangeDTO;
use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Models\Concerns\BelongsToOrganization;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\DeletionApplier;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('deletion_test_projects');

    Schema::create('deletion_test_projects', function (Blueprint $table) {
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
});

test('a deleted entity is soft-deleted and restored on re-upsert', function () {
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    app(SyncHandlerRegistry::class)->register(SyncEntityType::PROJECTS, DeletionTestHandler::class);

    $repository = new DeletionTestRepository;
    $repository->upsertMany($workspace, [['clockify_id' => 'p1', 'name' => 'Alpha']]);

    $applier = app(DeletionApplier::class);
    $applier->ingest($workspace, [
        new EntityChangeDTO(SyncEntityType::PROJECTS, 'p1', EntityChangeType::DELETED, CarbonImmutable::parse('2026-06-01 10:00:00')),
    ]);

    expect($applier->applyPending($workspace))->toBe(1);

    $project = DeletionTestProject::query()->where('clockify_id', 'p1')->first();

    expect($project?->deleted_at)->not->toBeNull();

    // A later create/update restores the row (delete→recreate converges).
    $repository->upsertMany($workspace, [['clockify_id' => 'p1', 'name' => 'Alpha']]);

    expect($project?->fresh()?->deleted_at)->toBeNull();

    // Re-applying the same deletion is a no-op.
    expect($applier->applyPending($workspace))->toBe(0);
});

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string|null $name
 * @property Carbon|null $deleted_at
 */
class DeletionTestProject extends Model
{
    use BelongsToOrganization;

    protected $table = 'deletion_test_projects';

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

class DeletionTestRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return DeletionTestProject::class;
    }
}

class DeletionTestHandler implements SyncHandler
{
    public function entityType(): SyncEntityType
    {
        return SyncEntityType::PROJECTS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    public function fetchPage(SyncContext $context, int $page): iterable
    {
        return [];
    }

    public function map(array $raw, SyncContext $context): array
    {
        return [
            'clockify_id' => (string) $raw['id'],
            'name' => isset($raw['name']) ? (string) $raw['name'] : null,
        ];
    }

    public function repository(): SyncUpsertRepositoryInterface
    {
        return new DeletionTestRepository;
    }

    public function delete(SyncContext $context, string $clockifyId): void
    {
        DeletionTestProject::query()
            ->where('workspace_id', $context->workspace->id)
            ->where('clockify_id', $clockifyId)
            ->update(['deleted_at' => now()]);
    }
}
