<?php

use App\DTOs\Sync\ImportPlan;
use App\DTOs\Sync\ImportPlanEstimate;
use App\DTOs\Sync\ImportPlanJob;
use App\DTOs\Sync\ImportPlanPhase;
use App\Enums\ApiUsageWindowType;
use App\Enums\SyncEntityType;
use App\Enums\SyncMode;
use App\Enums\SyncPhase;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Jobs\Sync\SyncEntityJob;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Models\Concerns\BelongsToOrganization;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use App\Services\Sync\SyncJobRunner;
use App\Services\Sync\SyncRunService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'clockify.retry.times' => 1,
        'clockify.retry.sleep' => 0,
        'clockify.sync_concurrency' => 10,
    ]);

    Schema::dropIfExists('run_flow_test_entries');

    Schema::create('run_flow_test_entries', function (Blueprint $table) {
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

test('a small run executes end to end and finalizes', function () {
    $connection = ClockifyConnection::factory()->free()->create();
    $workspace = ClockifyWorkspace::factory()
        ->for($connection, 'connection')
        ->create(['clockify_id' => 'ws-1']);

    app(SyncHandlerRegistry::class)->register(SyncEntityType::TIME_ENTRY, RunFlowTestHandler::class);

    Http::fake([
        '*/workspaces/ws-1/run-flow-entries*' => Http::response(
            [['id' => 'e1', 'description' => 'A']],
            200,
            ['Last-Page' => 'true'],
        ),
    ]);

    Queue::fake();

    $plan = new ImportPlan(
        mode: SyncMode::INITIAL,
        priority: SyncPriority::NORMAL,
        rangeStart: CarbonImmutable::parse('2026-05-01'),
        rangeEnd: CarbonImmutable::parse('2026-06-01'),
        pageSize: 200,
        phases: [
            new ImportPlanPhase(SyncPhase::FACT, [
                new ImportPlanJob(
                    SyncEntityType::TIME_ENTRY,
                    SyncPhase::FACT,
                    SyncPriority::NORMAL,
                    CarbonImmutable::parse('2026-05-01'),
                    CarbonImmutable::parse('2026-06-01'),
                    'u1',
                ),
            ]),
        ],
        estimate: new ImportPlanEstimate(1, 1, 1, ApiUsageWindowType::HOUR, 30, 3600, 3600),
    );

    $service = app(SyncRunService::class);
    $run = $service->startFromPlan($plan, $connection, $workspace);

    Queue::assertPushed(SyncEntityJob::class, 1);

    $job = $run->jobs()->first();

    (new SyncEntityJob($job->id))->handle(app(SyncJobRunner::class), $service);

    $run->refresh();

    expect($run->status)->toBe(SyncRunStatus::COMPLETED)
        ->and($run->records_created)->toBe(1)
        ->and($run->completed_at)->not->toBeNull()
        ->and(RunFlowTestEntry::query()->count())->toBe(1);
});

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string|null $name
 */
class RunFlowTestEntry extends Model
{
    use BelongsToOrganization;

    protected $table = 'run_flow_test_entries';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }
}

class RunFlowTestRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return RunFlowTestEntry::class;
    }
}

class RunFlowTestHandler implements SyncHandler
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
            ->get("/workspaces/{$context->workspace->clockify_id}/run-flow-entries", [
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
        return new RunFlowTestRepository;
    }

    public function delete(SyncContext $context, string $clockifyId): void
    {
        //
    }
}
