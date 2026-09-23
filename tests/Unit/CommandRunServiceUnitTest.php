<?php

use App\DTOs\Developer\CommandRunFilterDTO;
use App\Enums\CommandRunStatus;
use App\Jobs\Developer\RunMaintenanceCommand;
use App\Models\CommandRun;
use App\Repositories\Contracts\CommandRunRepositoryInterface;
use App\Services\Developer\CommandRunService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(CommandRunRepositoryInterface::class);
    $this->service = new CommandRunService($this->repository);
});

afterEach(function () {
    Mockery::close();
});

test('paginate calls the repository with the filter page size', function () {
    $filters = new CommandRunFilterDTO(perPage: 25);
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->repository->shouldReceive('paginate')
        ->once()
        ->with($filters, 25)
        ->andReturn($paginator);

    expect($this->service->paginate($filters))->toBe($paginator);
});

test('dispatch persists a pending run and queues the job', function () {
    Queue::fake();

    $this->repository->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['action'] === 'cache-clear'
            && $data['command'] === 'cache:clear'
            && $data['user_id'] === null))
        ->andReturn(new CommandRun([
            'action' => 'cache-clear',
            'command' => 'cache:clear',
            'status' => 'pending',
        ]));

    $run = $this->service->dispatch('cache-clear');

    expect($run)->toBeInstanceOf(CommandRun::class);

    Queue::assertPushed(RunMaintenanceCommand::class);
});

test('dispatch rejects unknown actions', function () {
    $this->repository->shouldReceive('create')->never();

    expect(fn () => $this->service->dispatch('nope'))
        ->toThrow(InvalidArgumentException::class);
});

test('markRunning sets the running state', function () {
    $run = CommandRun::factory()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(function (CommandRun $model, array $data) use ($run): bool {
            return $model->is($run)
                && $data['status'] === CommandRunStatus::RUNNING
                && $data['progress'] === 50;
        })
        ->andReturn($run);

    $this->service->markRunning($run);
});

test('markCompleted records the output and exit code', function () {
    $run = CommandRun::factory()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(function (CommandRun $model, array $data) use ($run): bool {
            return $model->is($run)
                && $data['status'] === CommandRunStatus::COMPLETED
                && $data['output'] === 'done'
                && $data['exit_code'] === 0
                && $data['progress'] === 100;
        })
        ->andReturn($run);

    $this->service->markCompleted($run, 'done', 0);
});

test('markFailed records the failure state and error message', function () {
    $run = CommandRun::factory()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(function (CommandRun $model, array $data) use ($run): bool {
            return $model->is($run)
                && $data['status'] === CommandRunStatus::FAILED
                && $data['error_message'] === 'boom'
                && $data['exit_code'] === 1;
        })
        ->andReturn($run);

    $this->service->markFailed($run, 'boom', null, 1);
});
