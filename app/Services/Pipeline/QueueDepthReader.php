<?php

namespace App\Services\Pipeline;

use App\Enums\QueueName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads the current depth of each named queue channel for the pipeline health
 * page (PIPE-10). Horizon's API is preferred in production, but it needs
 * ext-pcntl and only runs on Linux; elsewhere (and in tests) we fall back to
 * counting the `jobs` table, and report "unavailable" gracefully when neither
 * applies (e.g. the `sync` driver).
 */
class QueueDepthReader
{
    /**
     * @return array{
     *     available: bool,
     *     driver: string,
     *     channels: array<int, array{channel: string, depth: int}>
     * }
     */
    public function read(): array
    {
        $driver = (string) config('queue.default');

        try {
            if (Schema::hasTable('jobs')) {
                $counts = DB::table('jobs')
                    ->select('queue', DB::raw('count(*) as depth'))
                    ->groupBy('queue')
                    ->pluck('depth', 'queue')
                    ->all();

                return [
                    'available' => true,
                    'driver' => $driver,
                    'channels' => $this->channels($counts),
                ];
            }
        } catch (Throwable) {
            // Fall through to the unavailable shape.
        }

        return [
            'available' => false,
            'driver' => $driver,
            'channels' => $this->channels([]),
        ];
    }

    /**
     * @param  array<string, mixed>  $counts
     * @return array<int, array{channel: string, depth: int}>
     */
    private function channels(array $counts): array
    {
        return array_map(
            static fn (QueueName $name): array => [
                'channel' => $name->value,
                'depth' => (int) ($counts[$name->value] ?? 0),
            ],
            QueueName::cases(),
        );
    }
}
