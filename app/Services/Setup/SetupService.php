<?php

namespace App\Services\Setup;

use Ahs12\Setanjo\Facades\Settings;
use App\DTOs\Setup\SetupDTO;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class SetupService
{
    /**
     * The lock file (on the `local` disk) that marks the app as installed.
     */
    public const LOCK_FILE = 'installed';

    /**
     * Marker file that re-opens the wizard after a developer-requested reset.
     */
    public const RESET_MARKER = 'setup_reset';

    /**
     * PHP extensions the application needs to run.
     *
     * @var list<string>
     */
    public const REQUIRED_EXTENSIONS = [
        'pdo',
        'mbstring',
        'openssl',
        'tokenizer',
        'fileinfo',
        'curl',
        'xml',
        'zip',
    ];

    /**
     * Seeders required for a usable install (RBAC + settings). The super admin
     * is created by the wizard itself, so the demo user is never seeded.
     *
     * @var list<class-string>
     */
    public const REQUIRED_SEEDERS = [
        PermissionSeeder::class,
        RoleSeeder::class,
        SettingSeeder::class,
    ];

    public function __construct(
        protected UserRepositoryInterface $users,
        protected EnvironmentWriter $writer,
    ) {}

    /**
     * Whether the one-time setup has already been completed.
     */
    public function isComplete(): bool
    {
        // An explicit reset re-opens the wizard even when a lock file or an
        // existing super admin would otherwise mark the app as installed.
        if (Storage::disk('local')->exists(self::RESET_MARKER)) {
            return false;
        }

        if (Storage::disk('local')->exists(self::LOCK_FILE)) {
            return true;
        }

        try {
            if (Settings::has(SettingKey::SETUP_COMPLETED_AT->value)) {
                return true;
            }

            // Seeded installs (local/testing) already contain a super admin.
            return $this->users->hasSuperAdmin();
        } catch (Throwable) {
            // A fresh, unmigrated database should still present the wizard.
            return false;
        }
    }

    /**
     * Whether a super admin already exists (so the wizard can skip creating one).
     */
    public function hasSuperAdmin(): bool
    {
        try {
            return $this->users->hasSuperAdmin();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The existing super admin, if one has already been created.
     */
    public function existingSuperAdmin(): ?User
    {
        try {
            return $this->users->firstSuperAdmin();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Re-open the one-time setup wizard (development only).
     *
     * Clears the install lock and the completion flag, then writes a reset
     * marker so `isComplete()` returns false even when a super admin exists.
     *
     * By default the existing super admin is kept, so the wizard skips the
     * account step. Pass `$fresh` to delete every user instead, so onboarding
     * starts from scratch and creates a brand new super admin.
     *
     * @return array{lock_removed: bool, setting_removed: bool, users_deleted: int, has_super_admin: bool}
     */
    public function reset(bool $fresh = false): array
    {
        $lockRemoved = Storage::disk('local')->delete(self::LOCK_FILE);

        $settingRemoved = false;

        try {
            if (Settings::has(SettingKey::SETUP_COMPLETED_AT->value)) {
                Settings::forget(SettingKey::SETUP_COMPLETED_AT->value);
                $settingRemoved = true;
            }
        } catch (Throwable) {
            // The settings table may not exist yet on a fresh install.
        }

        $usersDeleted = $fresh ? $this->users->deleteAll() : 0;

        Storage::disk('local')->put(self::RESET_MARKER, now()->toIso8601String());

        return [
            'lock_removed' => $lockRemoved,
            'setting_removed' => $settingRemoved,
            'users_deleted' => $usersDeleted,
            'has_super_admin' => $this->hasSuperAdmin(),
        ];
    }

    /**
     * Environment requirements shown on the first step of the wizard.
     *
     * Requirements flagged `required` must pass before the wizard can proceed;
     * the rest are informational (e.g. the database is configured later).
     *
     * @return list<array{key: string, label: string, passed: bool, detail: string, required: bool}>
     */
    public function requirements(): array
    {
        $missing = $this->missingExtensions();

        return [
            $this->requirement('php', 'PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION),
            $this->requirement(
                'extensions',
                'Required PHP extensions',
                $missing === [],
                $missing === [] ? 'All loaded' : 'Missing: '.implode(', ', $missing),
            ),
            $this->requirement('storage', 'Storage directory writable', is_writable(storage_path()), is_writable(storage_path()) ? 'Writable' : 'Not writable'),
            $this->requirement('cache', 'Bootstrap cache writable', is_writable(base_path('bootstrap/cache')), is_writable(base_path('bootstrap/cache')) ? 'Writable' : 'Not writable'),
            $this->requirement('environment', 'Environment file writable', $this->writer->isWritable(), $this->writer->isWritable() ? 'Writable' : 'Not writable'),
            $this->requirement('key', 'Application key set', $this->appKeySet(), $this->appKeySet() ? 'Set' : 'Missing'),
            $this->requirement('database', 'Database connection', $this->databaseReachable(), $this->databaseReachable() ? 'Connected' : 'Not configured', required: false),
        ];
    }

    /**
     * The workspace name to prefill, falling back to the config value.
     */
    public function defaultAppName(): string
    {
        try {
            return (string) Settings::get(
                SettingKey::SYSTEM_NAME->value,
                config('app.name', 'Evoriq'),
            );
        } catch (Throwable) {
            return (string) config('app.name', 'Evoriq');
        }
    }

    /**
     * Snapshot of the installer's progress, used to drive the wizard UI.
     *
     * @return array{environment: array{env_exists: bool, env_writable: bool, key_set: bool}, database: array{configured: bool, migrated: bool, seeded: bool}}
     */
    public function status(): array
    {
        return [
            'environment' => [
                'env_exists' => $this->writer->exists(),
                'env_writable' => $this->writer->isWritable(),
                'key_set' => $this->appKeySet(),
            ],
            'database' => [
                'configured' => $this->databaseReachable(),
                'migrated' => $this->migrated(),
                'seeded' => $this->seeded(),
            ],
        ];
    }

    /**
     * Ensure an environment file exists and generate an application key.
     */
    public function generateAppKey(): string
    {
        $this->writer->ensureExists();

        if (! $this->writer->isWritable()) {
            throw new RuntimeException('The environment file is not writable.');
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        $this->writer->set(['APP_KEY' => $key]);
        config(['app.key' => $key]);

        return $key;
    }

    /**
     * Run every pending migration and the seeders required for a usable app.
     *
     * @return array{ok: bool, output: string}
     */
    public function migrate(): array
    {
        $output = new BufferedOutput;

        if (Artisan::call('migrate', ['--force' => true], $output) !== 0) {
            return ['ok' => false, 'output' => trim($output->fetch())];
        }

        $log = trim($output->fetch());

        foreach (self::REQUIRED_SEEDERS as $seeder) {
            $seedOutput = new BufferedOutput;

            if (Artisan::call('db:seed', ['--class' => $seeder, '--force' => true], $seedOutput) !== 0) {
                return ['ok' => false, 'output' => trim($seedOutput->fetch())];
            }

            $seeded = trim($seedOutput->fetch());

            if ($seeded !== '') {
                $log = trim($log.PHP_EOL.$seeded);
            }
        }

        return ['ok' => true, 'output' => $log];
    }

    /**
     * Create the public storage symlink (best effort on Windows).
     *
     * @return array{ok: bool, output: string}
     */
    public function storageLink(): array
    {
        $output = new BufferedOutput;

        try {
            $exitCode = Artisan::call('storage:link', ['--force' => true], $output);
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }

        return ['ok' => $exitCode === 0, 'output' => trim($output->fetch())];
    }

    /**
     * Whether the core tables exist.
     */
    public function migrated(): bool
    {
        try {
            return Schema::hasTable('migrations')
                && Schema::hasTable('users')
                && Schema::hasTable('roles')
                && Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the RBAC roles have been seeded.
     */
    public function seeded(): bool
    {
        try {
            return Schema::hasTable('roles') && DB::table('roles')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Complete setup: keep or create the super admin, then lock the app.
     */
    public function complete(SetupDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            // A super admin may already exist (e.g. a seeded install that was
            // reset for testing) — reuse it instead of creating a duplicate.
            $user = $this->existingSuperAdmin() ?? $this->createSuperAdmin($dto);

            Settings::set(SettingKey::SYSTEM_NAME->value, $dto->appName);
            Settings::set(SettingKey::SETUP_COMPLETED_AT->value, now()->toIso8601String());

            Storage::disk('local')->delete(self::RESET_MARKER);
            Storage::disk('local')->put(self::LOCK_FILE, now()->toIso8601String());

            return $user;
        });
    }

    /**
     * Create and verify a new super admin account.
     */
    private function createSuperAdmin(SetupDTO $dto): User
    {
        $user = $this->users->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        $user->markEmailAsVerified();
        $this->users->assignRoles($user, [UserRole::SUPER_ADMIN->value]);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function missingExtensions(): array
    {
        return array_values(array_filter(
            self::REQUIRED_EXTENSIONS,
            fn (string $extension): bool => ! extension_loaded($extension),
        ));
    }

    private function appKeySet(): bool
    {
        return is_string(config('app.key')) && config('app.key') !== '';
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, required: bool}
     */
    private function requirement(string $key, string $label, bool $passed, string $detail, bool $required = true): array
    {
        return compact('key', 'label', 'passed', 'detail', 'required');
    }
}
