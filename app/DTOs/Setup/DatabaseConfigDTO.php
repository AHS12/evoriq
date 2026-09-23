<?php

namespace App\DTOs\Setup;

use App\Enums\DatabaseDriver;
use App\Http\Requests\Setup\StoreDatabaseRequest;

final readonly class DatabaseConfigDTO
{
    public function __construct(
        public DatabaseDriver $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public bool $createDatabase,
    ) {}

    public static function fromRequest(StoreDatabaseRequest $request): self
    {
        $data = $request->validated();
        $driver = DatabaseDriver::from($data['driver']);

        return new self(
            driver: $driver,
            host: (string) ($data['host'] ?? ''),
            port: (int) ($data['port'] ?? $driver->defaultPort() ?? 0),
            database: (string) ($data['database'] ?? ''),
            username: (string) ($data['username'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            createDatabase: (bool) ($data['create_database'] ?? false),
        );
    }

    /**
     * @return array<string, string|int|bool>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver->value,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'create_database' => $this->createDatabase,
        ];
    }
}
