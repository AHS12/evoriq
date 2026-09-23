<?php

namespace App\Providers;

use App\Enums\SettingKey;
use App\Services\Setting\SettingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Applies database-backed settings (general + mail) to the runtime config.
 *
 * Falls back to `.env` values when a setting has not been configured yet, and
 * never breaks boot when the database is unavailable (e.g. during migrations).
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = $this->app->make(SettingService::class);

            $this->applyGeneral($settings);
            $this->applyMail($settings);
        } catch (Throwable) {
            // Ignore: the database may not be reachable yet.
        }
    }

    private function applyGeneral(SettingService $settings): void
    {
        if ($settings->has(SettingKey::SYSTEM_NAME)) {
            config(['app.name' => $settings->get(SettingKey::SYSTEM_NAME)]);
        }

        if ($settings->has(SettingKey::TIMEZONE)) {
            $timezone = (string) $settings->get(SettingKey::TIMEZONE);

            if (in_array($timezone, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        }
    }

    private function applyMail(SettingService $settings): void
    {
        if ($settings->has(SettingKey::MAIL_MAILER)) {
            config(['mail.default' => $settings->get(SettingKey::MAIL_MAILER)]);
        }

        $smtp = [];

        if ($settings->has(SettingKey::MAIL_HOST)) {
            $smtp['host'] = $settings->get(SettingKey::MAIL_HOST);
        }

        if ($settings->has(SettingKey::MAIL_PORT)) {
            $smtp['port'] = (int) $settings->get(SettingKey::MAIL_PORT);
        }

        if ($settings->has(SettingKey::MAIL_USERNAME)) {
            $smtp['username'] = $settings->get(SettingKey::MAIL_USERNAME);
        }

        if ($settings->has(SettingKey::MAIL_PASSWORD)) {
            $smtp['password'] = $settings->get(SettingKey::MAIL_PASSWORD);
        }

        $encryption = $settings->has(SettingKey::MAIL_ENCRYPTION)
            ? $settings->get(SettingKey::MAIL_ENCRYPTION)
            : null;

        if (is_string($encryption) && $encryption !== '' && $encryption !== 'none') {
            $smtp['encryption'] = $encryption;
        }

        foreach ($smtp as $key => $value) {
            config(["mail.mailers.smtp.{$key}" => $value]);
        }

        if ($settings->has(SettingKey::MAIL_FROM_ADDRESS)) {
            config(['mail.from.address' => $settings->get(SettingKey::MAIL_FROM_ADDRESS)]);
        }

        if ($settings->has(SettingKey::MAIL_FROM_NAME)) {
            config(['mail.from.name' => $settings->get(SettingKey::MAIL_FROM_NAME)]);
        }
    }
}
