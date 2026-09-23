<?php

namespace App\Services\Developer;

use App\DTOs\Developer\CommandRunDTO;
use App\DTOs\Developer\CommandRunFilterDTO;
use App\Enums\CommandRunStatus;
use App\Jobs\Developer\RunMaintenanceCommand;
use App\Models\CommandRun;
use App\Registry\MaintenanceActionRegistry;
use App\Repositories\Contracts\CommandRunRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommandRunService
{
    public function __construct(
        protected CommandRunRepositoryInterface $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CommandRun>
     */
    public function paginate(CommandRunFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $filters->perPage);
    }

    public function find(int $id): ?CommandRun
    {
        return $this->repository->findById($id);
    }

    /**
     * @return Collection<int, CommandRun>
     */
    public function recent(int $limit = 10): Collection
    {
        return $this->repository->recent($limit);
    }

    /**
     * Queue a maintenance action and return its run record.
     *
     * @throws InvalidArgumentException When the action is not registered.
     */
    public function dispatch(string $action): CommandRun
    {
        $definition = MaintenanceActionRegistry::get($action);

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown maintenance action [{$action}].");
        }

        $attributes = CommandRunDTO::forAction($action, $definition['command'])->toArray();
        $attributes['user_id'] = auth()->id();

        $run = DB::transaction(
            fn (): CommandRun => $this->repository->create($attributes),
        );

        RunMaintenanceCommand::dispatch($run);

        return $run;
    }

    /**
     * Mark a run as executing.
     */
    public function markRunning(CommandRun $run): CommandRun
    {
        return $this->repository->update($run, [
            'status' => CommandRunStatus::RUNNING,
            'progress' => 50,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark a run as completed with its captured output.
     */
    public function markCompleted(CommandRun $run, string $output, int $exitCode): CommandRun
    {
        return $this->repository->update($run, [
            'status' => CommandRunStatus::COMPLETED,
            'progress' => 100,
            'output' => $output,
            'exit_code' => $exitCode,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark a run as failed and record the error.
     */
    public function markFailed(
        CommandRun $run,
        string $message,
        ?string $output = null,
        ?int $exitCode = null,
    ): CommandRun {
        return $this->repository->update($run, [
            'status' => CommandRunStatus::FAILED,
            'progress' => 100,
            'output' => $output,
            'exit_code' => $exitCode,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}
