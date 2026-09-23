<?php

namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class DatabaseMigrationCheck extends Check
{
    /**
     * Run the health check.
     */
    public function run(): Result
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return Result::make()->warning('The migration repository does not exist.');
        }

        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        if ($pending === []) {
            return Result::make()->ok('All migrations are up to date.');
        }

        return Result::make()->warning('Pending migrations: '.implode(', ', $pending));
    }
}
