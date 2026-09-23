<?php

namespace Database\Seeders;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed the default global settings (without overwriting existing values).
     */
    public function run(): void
    {
        foreach (SettingKey::cases() as $key) {
            // Settings without a default are managed by the application
            // (e.g. the one-time setup flag), never seeded.
            if ($key->defaultValue() === null) {
                continue;
            }

            if (Settings::has($key->value)) {
                continue;
            }

            Settings::set($key->value, $key->defaultValue());
        }
    }
}
