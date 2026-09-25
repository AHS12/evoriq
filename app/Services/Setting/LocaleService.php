<?php

namespace App\Services\Setting;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\AppLocale;
use App\Enums\SettingKey;
use App\Enums\UserSettingKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class LocaleService
{
    /**
     * The locale stored for the user, if any.
     */
    public function storedForUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $locale = $user->settings()->get(UserSettingKey::LOCALE->value);

        return AppLocale::tryFrom(is_string($locale) ? $locale : '')?->value;
    }

    /**
     * The global default locale for guests and new users.
     */
    public function default(): string
    {
        try {
            $locale = Settings::get(
                SettingKey::APP_LOCALE->value,
                SettingKey::APP_LOCALE->defaultValue(),
            );
        } catch (Throwable) {
            // The database may not exist yet (first-run installer); fall back
            // to the configured locale instead of failing the request.
            $locale = null;
        }

        if (is_string($locale) && ($case = AppLocale::tryFrom($locale)) !== null) {
            return $case->value;
        }

        return config('app.locale', 'en');
    }

    /**
     * Persist the user's locale preference.
     */
    public function update(User $user, string $locale): void
    {
        DB::transaction(function () use ($user, $locale): void {
            $user->settings()->set(UserSettingKey::LOCALE->value, $locale);
        });
    }

    /**
     * The application dictionary for a locale, with any missing keys filled
     * from the fallback locale (en).
     *
     * @return array<string, string>
     */
    public function dictionary(string $locale): array
    {
        $fallback = $this->readJson(config('app.fallback_locale', 'en'));

        if ($locale === config('app.fallback_locale', 'en')) {
            return $fallback;
        }

        return array_merge($fallback, $this->readJson($locale));
    }

    /**
     * @return array<string, string>
     */
    private function readJson(string $locale): array
    {
        $path = lang_path("app/{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
