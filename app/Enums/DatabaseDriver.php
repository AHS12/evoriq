<?php

namespace App\Enums;

enum DatabaseDriver: string
{
    case PGSQL = 'pgsql';
    case MYSQL = 'mysql';
    case MARIADB = 'mariadb';
    case SQLITE = 'sqlite';

    public function label(): string
    {
        return match ($this) {
            self::PGSQL => 'PostgreSQL',
            self::MYSQL => 'MySQL',
            self::MARIADB => 'MariaDB',
            self::SQLITE => 'SQLite',
        };
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::PGSQL => 5432,
            self::MYSQL, self::MARIADB => 3306,
            self::SQLITE => null,
        };
    }

    /**
     * Whether the driver needs a host, username and password.
     */
    public function requiresCredentials(): bool
    {
        return $this !== self::SQLITE;
    }

    /**
     * Whether the driver stores everything in a single file.
     */
    public function isFileBased(): bool
    {
        return $this === self::SQLITE;
    }

    /**
     * The PDO extension required to connect with this driver.
     */
    public function pdoExtension(): string
    {
        return match ($this) {
            self::PGSQL => 'pdo_pgsql',
            self::MYSQL, self::MARIADB => 'pdo_mysql',
            self::SQLITE => 'pdo_sqlite',
        };
    }

    /**
     * Driver metadata for the setup wizard.
     *
     * @return list<array{value: string, label: string, defaultPort: int|null, requiresCredentials: bool, available: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $driver): array => [
            'value' => $driver->value,
            'label' => $driver->label(),
            'defaultPort' => $driver->defaultPort(),
            'requiresCredentials' => $driver->requiresCredentials(),
            'available' => extension_loaded($driver->pdoExtension()),
        ], self::cases());
    }
}
