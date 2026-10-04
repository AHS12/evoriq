<?php

use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Enums\SyncPhase;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Models\Concerns\BelongsToOrganization;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use App\Services\Sync\SyncJobRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);

    Schema::dropIfExists('sync_resume_test_entries');

    Schema::create('sync_resume_test_entries', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('organization_id')->nullable();
        $table->unsignedBigInteger('workspace_id')->nullable();
        $table->string('clockify_id');
        $table->string('name')->nullable();
        $table->timestamp('synced_at')->nullable();
        $table->timestamps();

        $table->unique(['organization_id', 'workspace_id', 'clockify_id']);
    });
});

test('a failed page is retried from the checkpoint without duplicates', function () {
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()
        ->for($connection, 'connection')
        ->create(['clockify_id' => 'ws-1']);

    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
    ]);

    $job = ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'entity_type' => SyncEntityType::TIME_ENTRY,
        'page' => 0,
        'page_size' => 2,
    ]);

    app(SyncHandlerRegistry::class)->register(SyncEntityType::TIME_ENTRY, ResumeTestHandler::class);

    Http::fake([
        '*/workspaces/ws-1/test-entries*' => Http::sequence()
            ->push([['id' => 'e1', 'description' => 'A'], ['id' => 'e2', 'description' => 'B']], 200)
            ->push(['message' => 'boom'], 500)
            ->push([['id' => 'e3', 'description' => 'C']], 200),
    ]);

    $runner = app(SyncJobRunner::class);

    // First run: page 1 commits, page 2 fails before anything is upserted.
    expect(fn () => $runner->run($job))->toThrow(RuntimeException::class);

    $job->refresh();

    expect($job->page)->toBe(1)
        ->and($job->records_created)->toBe(2)
        ->and(ResumeTestEntry::query()->count())->toBe(2);

    // Second run: resumes at page 2 and completes without re-fetching page 1.
    $runner->run($job);

    $job->refresh();

    expect($job->page)->toBe(2)
        ->and($job->records_created)->toBe(3)
        ->and($job->status)->toBe(SyncJobStatus::COMPLETED)
        ->and(ResumeTestEntry::query()->count())->toBe(3);
});

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string|null $name
 */
class ResumeTestEntry extends Model
{
    use BelongsToOrganization;

    protected $table = 'sync_resume_test_entries';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }
}

class ResumeTestRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return ResumeTestEntry::class;
    }
}

class ResumeTestHandler implements SyncHandler
{
    public function entityType(): SyncEntityType
    {
        return SyncEntityType::TIME_ENTRY;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::FACT;
    }

    public function fetchPage(SyncContext $context, int $page): iterable
    {
        $response = app(ClockifyClient::class)
            ->forConnection($context->connection, $context->workspace)
            ->get("/workspaces/{$context->workspace->clockify_id}/test-entries", [
                'page' => $page,
                'page-size' => $context->pageSize,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('The page could not be fetched.');
        }

        return $response->json() ?? [];
    }

    public function map(array $raw, SyncContext $context): array
    {
        return [
            'clockify_id' => (string) $raw['id'],
            'name' => isset($raw['description']) ? (string) $raw['description'] : null,
        ];
    }

    public function repository(): SyncUpsertRepositoryInterface
    {
        return new ResumeTestRepository;
    }

    public function delete(SyncContext $context, string $clockifyId): void
    {
        //
    }
}
