<?php

namespace App\Services\Setup;

use App\DTOs\Setup\DatabaseConfigDTO;
use App\Enums\DatabaseDriver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Tests, provisions and persists the application database connection.
 *
 * This is setup infrastructure: it talks to the database server directly to
 * verify credentials (and optionally create the database) before the app has a
 * working connection. It never touches application models.
 */
class DatabaseConfigurator
{
    public function __construct(
        protected EnvironmentWriter $writer,
    ) {}

    /**
     * @return list<array{value: string, label: string, defaultPort: int|null, requiresCredentials: bool, available: bool}>
     */
    public function drivers(): array
    {
        return DatabaseDriver::options();
    }

    /**
     * Current environment values, used as form defaults.
     *
     * @return array{driver: string, host: string, port: int, database: string, username: string, password: string}
     */
    public function current(): array
    {
        $values = $this->writer->read();

        return [
            'driver' => $values['DB_CONNECTION'] ?? (string) config('database.default', 'pgsql'),
            'host' => $values['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($values['DB_PORT'] ?? 5432),
            'database' => $values['DB_DATABASE'] ?? 'evoriq',
            'username' => $values['DB_USERNAME'] ?? 'postgres',
            'password' => '',
        ];
    }

    /**
     * Whether the currently configured connection is reachable.
     */
    public function configured(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Probe a connection without persisting anything.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(DatabaseConfigDTO $dto): array
    {
        try {
            $this->probe($dto);

            return ['ok' => true, 'message' => 'Connection established.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->cleanMessage($e->getMessage())];
        }
    }

    /**
     * Test the connection, creating the database first when requested.
     *
     * @return array{ok: bool, created: bool, message: string}
     */
    public function provision(DatabaseConfigDTO $dto): array
    {
        $test = $this->test($dto);

        if ($test['ok']) {
            return ['ok' => true, 'created' => false, 'message' => 'Connected to the database.'];
        }

        if (! $dto->createDatabase) {
            return ['ok' => false, 'created' => false, 'message' => $test['message']];
        }

        try {
            $this->createDatabase($dto);
        } catch (Throwable $e) {
            // A "database already exists" race is resolved by re-testing below.
            $retest = $this->test($dto);

            if (! $retest['ok']) {
                return ['ok' => false, 'created' => false, 'message' => $this->cleanMessage($e->getMessage())];
            }
        }

        $retest = $this->test($dto);

        return [
            'ok' => $retest['ok'],
            'created' => true,
            'message' => $retest['ok'] ? 'Database created and connected.' : $retest['message'],
        ];
    }

    /**
     * Persist the configuration and reload it in the current process.
     */
    public function write(DatabaseConfigDTO $dto, bool $reload = true): void
    {
        if ($dto->driver->isFileBased()) {
            $path = $this->sqlitePath($dto);

            if (! is_file($path)) {
                touch($path);
            }
        }

        $this->writer->set([
            'DB_CONNECTION' => $dto->driver->value,
            'DB_HOST' => $dto->host,
            'DB_PORT' => $dto->port,
            'DB_DATABASE' => $dto->driver->isFileBased() ? $this->sqlitePath($dto) : $dto->database,
            'DB_USERNAME' => $dto->username,
            'DB_PASSWORD' => $dto->password,
        ]);

        if ($reload) {
            $this->reload($dto);
        }
    }

    /**
     * Create the database on the server (no-op for SQLite).
     */
    protected function createDatabase(DatabaseConfigDTO $dto): void
    {
        if ($dto->driver->isFileBased()) {
            $path = $this->sqlitePath($dto);

            if (! is_file($path)) {
                touch($path);
            }

            return;
        }

        $pdo = new PDO(
            $this->serverDsn($dto),
            $dto->username,
            $dto->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );

        $name = $dto->database;

        $statement = match ($dto->driver) {
            DatabaseDriver::PGSQL => 'CREATE DATABASE "'.str_replace('"', '""', $name).'"',
            DatabaseDriver::MYSQL, DatabaseDriver::MARIADB => 'CREATE DATABASE `'.str_replace('`', '``', $name).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            DatabaseDriver::SQLITE => '',
        };

        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    protected function probe(DatabaseConfigDTO $dto): void
    {
        config(['database.connections.setup_probe' => $this->connectionConfig($dto)]);

        DB::purge('setup_probe');

        try {
            DB::connection('setup_probe')->getPdo();
        } finally {
            DB::purge('setup_probe');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function connectionConfig(DatabaseConfigDTO $dto): array
    {
        if ($dto->driver->isFileBased()) {
            return [
                'driver' => 'sqlite',
                'database' => $this->sqlitePath($dto),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => $dto->driver->value,
            'host' => $dto->host,
            'port' => $dto->port,
            'database' => $dto->database,
            'username' => $dto->username,
            'password' => $dto->password,
            'charset' => $dto->driver === DatabaseDriver::PGSQL ? 'utf8' : 'utf8mb4',
            'collation' => $dto->driver === DatabaseDriver::PGSQL ? null : 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'strict' => true,
        ];
    }

    protected function serverDsn(DatabaseConfigDTO $dto): string
    {
        return match ($dto->driver) {
            DatabaseDriver::PGSQL => "pgsql:host={$dto->host};port={$dto->port};dbname=postgres",
            DatabaseDriver::MYSQL, DatabaseDriver::MARIADB => "mysql:host={$dto->host};port={$dto->port}",
            DatabaseDriver::SQLITE => 'sqlite::memory:',
        };
    }

    protected function reload(DatabaseConfigDTO $dto): void
    {
        $this->clearConfigCache();

        $driver = $dto->driver->value;

        config([
            'database.default' => $driver,
            "database.connections.{$driver}" => array_merge(
                (array) config("database.connections.{$driver}", []),
                $this->connectionConfig($dto),
            ),
        ]);

        DB::purge($driver);
        DB::setDefaultConnection($driver);
    }

    protected function sqlitePath(DatabaseConfigDTO $dto): string
    {
        $database = trim($dto->database);

        if ($database === '' || $database === 'database/database.sqlite') {
            return database_path('database.sqlite');
        }

        if ($database === ':memory:' || str_starts_with($database, 'file:')) {
            return $database;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $database) === 1 || str_starts_with($database, '/')) {
            return $database;
        }

        return base_path($database);
    }

    protected function clearConfigCache(): void
    {
        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
            // The runtime config is updated manually below regardless.
        }
    }

    protected function cleanMessage(string $message): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $message));
    }
}
