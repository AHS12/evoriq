<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class PintCommand extends Command
{
    protected $signature = 'pint {--test : Run Pint in test mode without fixing files}';

    protected $description = 'Run Laravel Pint to fix code style issues';

    public function handle(): int
    {
        $command = ['php', 'vendor/bin/pint', '--ansi'];

        if ($this->option('test')) {
            $command[] = '--test';
        }

        $process = new Process($command);
        $process->setTimeout(null);

        $process->setEnv([
            'TERM' => getenv('TERM') ?: 'xterm-256color',
            'COLORTERM' => getenv('COLORTERM') ?: 'truecolor',
        ]);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
