<?php

use App\Contracts\PipelineRunnable;
use App\Enums\PipelineRunType;
use App\Models\PipelineEvent;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use App\Services\Pipeline\PipelineEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A minimal run whose stream the recorder can write to.
 */
function makePipelineRunStub(string $runId = 'run-1', PipelineRunType $type = PipelineRunType::DATA_PROCESSING): PipelineRunnable
{
    return new class($runId, $type) implements PipelineRunnable
    {
        public function __construct(
            private readonly string $id,
            private readonly PipelineRunType $runType,
        ) {}

        public function pipelineRunType(): PipelineRunType
        {
            return $this->runType;
        }

        public function pipelineRunId(): string
        {
            return $this->id;
        }
    };
}

beforeEach(function () {
    $this->repository = Mockery::mock(PipelineEventRepositoryInterface::class);
    $this->repository->shouldReceive('nextSequence')->andReturn(1)->byDefault();
    $this->repository->shouldReceive('append')->andReturn(new PipelineEvent)->byDefault();
    $this->repository->shouldReceive('latestProgressForRun')->andReturn(null)->byDefault();

    $this->recorder = new PipelineEventRecorder($this->repository);
});

afterEach(function () {
    Mockery::close();
});

test('records events with the run type and id and an increasing sequence', function () {
    $appended = [];

    $this->repository->shouldReceive('nextSequence')->andReturn(1, 2);
    $this->repository->shouldReceive('append')->twice()->andReturnUsing(function (array $data) use (&$appended): PipelineEvent {
        $appended[] = $data;

        return new PipelineEvent($data);
    });

    $run = makePipelineRunStub('42');

    $this->recorder->dispatched($run);
    $this->recorder->started($run, 'Reading file');

    expect($appended)->toHaveCount(2)
        ->and($appended[0]['sequence'])->toBe(1)
        ->and($appended[1]['sequence'])->toBe(2)
        ->and($appended[0]['run_type'])->toBe('data_processing')
        ->and($appended[0]['run_id'])->toBe('42')
        ->and($appended[0]['type'])->toBe('dispatched')
        ->and($appended[1]['type'])->toBe('started')
        ->and($appended[1]['stage'])->toBe('Reading file');
});

test('coalesces rapid progress events but writes when the stage changes', function () {
    $run = makePipelineRunStub('7');

    $last = new PipelineEvent([
        'run_type' => 'data_processing',
        'run_id' => '7',
        'sequence' => 1,
        'type' => 'progress',
        'level' => 'info',
        'stage' => 'Importing rows',
        'progress' => ['total' => 1000, 'processed' => 100, 'percentage' => 10.0],
        'occurred_at' => now(),
    ]);

    $this->repository->shouldReceive('latestProgressForRun')->andReturn($last);
    $this->repository->shouldReceive('append')->once()->andReturn(new PipelineEvent);

    // Same stage, same 5% bucket, recent: coalesced away.
    $this->recorder->progress($run, 110, 1000, 'Importing rows');

    // Stage changed: written.
    $this->recorder->progress($run, 120, 1000, 'Finalizing');
});

test('writes progress when the percentage crosses a bucket boundary', function () {
    $run = makePipelineRunStub('7');

    $last = new PipelineEvent([
        'run_type' => 'data_processing',
        'run_id' => '7',
        'sequence' => 1,
        'type' => 'progress',
        'level' => 'info',
        'stage' => 'Importing rows',
        'progress' => ['total' => 1000, 'processed' => 100, 'percentage' => 10.0],
        'occurred_at' => now(),
    ]);

    $this->repository->shouldReceive('latestProgressForRun')->andReturn($last);
    $this->repository->shouldReceive('append')->once()->andReturn(new PipelineEvent);

    // 20% crosses out of the 10% bucket.
    $this->recorder->progress($run, 200, 1000, 'Importing rows');
});

test('writes progress once the minimum interval has elapsed', function () {
    Carbon::setTestNow(now());

    $run = makePipelineRunStub('7');

    $last = new PipelineEvent([
        'run_type' => 'data_processing',
        'run_id' => '7',
        'sequence' => 1,
        'type' => 'progress',
        'level' => 'info',
        'stage' => 'Importing rows',
        'progress' => ['total' => 1000, 'processed' => 100, 'percentage' => 10.0],
        'occurred_at' => now()->subSeconds(10),
    ]);

    $this->repository->shouldReceive('latestProgressForRun')->andReturn($last);
    $this->repository->shouldReceive('append')->once()->andReturn(new PipelineEvent);

    $this->recorder->progress($run, 100, 1000, 'Importing rows');

    Carbon::setTestNow();
});

test('redacts configured sensitive keys from the context at any depth', function () {
    $appended = null;

    $this->repository->shouldReceive('append')->once()->andReturnUsing(function (array $data) use (&$appended): PipelineEvent {
        $appended = $data;

        return new PipelineEvent($data);
    });

    $this->recorder->stageStarted(makePipelineRunStub('3'), 'fetch', [
        'api_key' => 'secret-value',
        'Authorization' => 'Bearer abc',
        'nested' => ['token' => 'xyz', 'keep' => 'ok'],
        'keep' => 'value',
    ]);

    expect($appended['context'])
        ->not->toHaveKey('api_key')
        ->not->toHaveKey('Authorization')
        ->and($appended['context']['nested'])->not->toHaveKey('token')
        ->and($appended['context']['nested']['keep'])->toBe('ok')
        ->and($appended['context']['keep'])->toBe('value');
});

test('convenience methods map to the right type and level', function () {
    $types = [];

    $this->repository->shouldReceive('append')->andReturnUsing(function (array $data) use (&$types): PipelineEvent {
        $types[$data['type']] = $data['level'];

        return new PipelineEvent($data);
    });

    $run = makePipelineRunStub('5');

    $this->recorder->completed($run);
    $this->recorder->failed($run, 'boom');
    $this->recorder->warning($run, 'careful');
    $this->recorder->cancelled($run);

    expect($types)->toMatchArray([
        'completed' => 'success',
        'failed' => 'error',
        'warning' => 'warning',
        'cancelled' => 'warning',
    ]);
});
