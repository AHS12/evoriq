<?php

namespace App\Services\Setting;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class SettingService
{
    /**
     * Every configurable setting, grouped for the UI.
     *
     * @return array<int, array{key: string, label: string, fields: array<int, array<string, mixed>>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (SettingKey::groups() as $groupKey => $groupLabel) {
            $groups[] = [
                'key' => $groupKey,
                'label' => $groupLabel,
                'fields' => array_map(
                    fn (SettingKey $key): array => $this->field($key),
                    SettingKey::forGroup($groupKey),
                ),
            ];
        }

        return $groups;
    }

    /**
     * A single setting's metadata and (safe) value.
     *
     * @return array{key: string, label: string, description: string, type: string, group: string, value: mixed, is_secret: bool, has_value: bool, options: array<int, array{label: string, value: string}>}
     */
    public function field(SettingKey $key): array
    {
        $stored = Settings::get($key->value, $key->defaultValue());

        return [
            'key' => $key->value,
            'label' => $key->label(),
            'description' => $key->description(),
            'type' => $key->type(),
            'group' => $key->group(),
            'value' => $key->isSecret() ? null : $stored,
            'is_secret' => $key->isSecret(),
            'has_value' => $stored !== null && $stored !== '',
            'options' => $key->options(),
        ];
    }

    /**
     * Persist the submitted values, encrypting secrets. Blank secrets are kept.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (SettingKey::configurable() as $key) {
                if (! array_key_exists($key->value, $values)) {
                    continue;
                }

                $value = $values[$key->value];

                if ($key->isSecret() && ($value === null || $value === '')) {
                    continue;
                }

                if ($key->isSecret()) {
                    $value = Crypt::encryptString((string) $value);
                }

                Settings::set($key->value, $value);
            }
        });
    }

    /**
     * Resolve a setting's value, decrypting secrets.
     */
    public function get(SettingKey $key): mixed
    {
        $value = Settings::get($key->value, $key->defaultValue());

        if ($key->isSecret() && is_string($value) && $value !== '') {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return null;
            }
        }

        return $value;
    }

    public function has(SettingKey $key): bool
    {
        $value = Settings::get($key->value);

        return $value !== null && $value !== '';
    }
}
