<?php

namespace App\Console\Commands;

use App\DTOs\Setup\DriverConfigDTO;
use App\Enums\CacheDriver;
use App\Enums\QueueDriver;
use App\Enums\SessionDriver;
use App\Services\Setup\DriverConfigurator;
use Illuminate\Console\Command;
use ValueError;

class ConfigureDriversCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:configure-drivers
                            {--session= : Session driver (file, database, redis)}
                            {--cache= : Cache driver (file, database, redis)}
                            {--queue= : Queue driver (sync, database, redis)}
                            {--redis-host= : Redis host}
                            {--redis-port= : Redis port}
                            {--redis-password= : Redis password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect Redis and configure the session, cache and queue drivers';

    /**
     * Execute the console command.
     */
    public function handle(DriverConfigurator $drivers): int
    {
        $redis = $drivers->redis();
        $recommended = $drivers->recommended();

        $this->line($redis['available']
            ? '<info>'.$redis['message'].'</info>'
            : '<comment>'.$redis['message'].' Falling back to database/file drivers.</comment>');

        try {
            $dto = new DriverConfigDTO(
                session: SessionDriver::from((string) ($this->option('session') ?: $recommended['session'])),
                cache: CacheDriver::from((string) ($this->option('cache') ?: $recommended['cache'])),
                queue: QueueDriver::from((string) ($this->option('queue') ?: $recommended['queue'])),
                redisHost: (string) ($this->option('redis-host') ?: '127.0.0.1'),
                redisPort: (int) ($this->option('redis-port') ?: 6379),
                redisPassword: $this->option('redis-password') ?: null,
            );
        } catch (ValueError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $drivers->write($dto);

        $this->table(
            ['Driver', 'Value'],
            [
                ['Session', $dto->session->value],
                ['Cache', $dto->cache->value],
                ['Queue', $dto->queue->value],
            ],
        );

        return self::SUCCESS;
    }
}
