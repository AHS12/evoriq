<?php

namespace App\Providers;

use App\Services\Setup\EnvironmentWriter;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Bootstraps the environment before the first request so a fresh checkout can
 * reach the `/setup` wizard: it seeds `.env` from `.env.example` and generates
 * an application key when one is missing.
 */
class SetupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Cached configuration is authoritative and must not be rewritten.
        if ($this->app->configurationIsCached()) {
            return;
        }

        if (is_string(config('app.key')) && config('app.key') !== '') {
            return;
        }

        try {
            $writer = $this->app->make(EnvironmentWriter::class);
            $created = ! $writer->exists();
            $writer->ensureExists();

            if (! $writer->isWritable()) {
                return;
            }

            $key = 'base64:'.base64_encode(random_bytes(32));

            $writer->set(['APP_KEY' => $key]);
            config(['app.key' => $key]);

            // A freshly created environment file is not loaded until the next
            // request, so apply its dependency-free driver defaults now to keep
            // the setup wizard reachable before the database is migrated.
            if ($created) {
                config([
                    'session.driver' => 'file',
                    'cache.default' => 'file',
                    'queue.default' => 'database',
                ]);
            }
        } catch (Throwable) {
            // The setup wizard surfaces a missing key as a failing requirement.
        }
    }
}
