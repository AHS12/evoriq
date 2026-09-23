<?php

namespace App\Console\Commands;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use Illuminate\Console\Command;

class SyncSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync missing settings from the SettingKey enum defaults';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $created = 0;
        $total = 0;

        foreach (SettingKey::cases() as $key) {
            // Settings without a default are managed by the application
            // (e.g. the one-time setup flag), never synced.
            if ($key->defaultValue() === null) {
                continue;
            }

            $total++;

            if (Settings::has($key->value)) {
                continue;
            }

            Settings::set($key->value, $key->defaultValue());
            $created++;
        }

        $this->info(sprintf('Settings synchronized: %d created, %d total.', $created, $total));

        return self::SUCCESS;
    }
}
