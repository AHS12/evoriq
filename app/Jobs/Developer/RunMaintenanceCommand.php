<?php

namespace App\Jobs\Developer;

use App\Enums\QueueName;
use App\Models\CommandRun;
use App\Registry\MaintenanceActionRegistry;
use App\Registry\QueueRegistry;
use App\Services\Developer\CommandRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class RunMaintenanceCommand implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(public CommandRun $commandRun)
    {
        $definition = MaintenanceActionRegistry::get($commandRun->action);
        $queue = $definition !== null ? $definition['queue'] : QueueName::DEFAULT;

        $config = QueueRegistry::get($queue);

        $this->tries = $config->tries;
        $this->timeout = $config->timeout;

        $this->onQueue($config->name);
    }

    /**
     * Execute the job.
     */
    public function handle(CommandRunService $service): void
    {
        $run = $this->commandRun;

        $service->markRunning($run);

        $buffer = new BufferedOutput;

        try {
            $exitCode = Artisan::call($run->command, [], $buffer);
            $output = trim($buffer->fetch());

            if ($exitCode !== 0) {
                $service->markFailed(
                    $run,
                    $output !== '' ? $output : "The command [{$run->command}] failed.",
                    $output,
                    $exitCode,
                );

                return;
            }

            $service->markCompleted($run, $output, $exitCode);
        } catch (Throwable $e) {
            $service->markFailed($run, $e->getMessage(), trim($buffer->fetch()), null);

            throw $e;
        }
    }
}
