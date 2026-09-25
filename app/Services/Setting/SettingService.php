<?php

namespace App\Services\Setting;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\SettingKey;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class SettingService
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

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
     * Persist the submitted values, encrypting secrets, and audit every
     * actual change (secret values are never recorded).
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $changes = DB::transaction(function () use ($values): array {
            $changes = [];

            foreach (SettingKey::configurable() as $key) {
                if (! array_key_exists($key->value, $values)) {
                    continue;
                }

                $value = $values[$key->value];

                if ($key->isSecret() && ($value === null || $value === '')) {
                    continue;
                }

                $previous = Settings::get($key->value);

                if ($key->isSecret()) {
                    $value = Crypt::encryptString((string) $value);
                }

                if ($previous === $value) {
                    continue;
                }

                Settings::set($key->value, $value);

                $changes[] = [
                    'key' => $key->value,
                    'old' => $key->isSecret() ? null : $previous,
                    'new' => $key->isSecret() ? null : $value,
                ];
            }

            return $changes;
        });

        foreach ($changes as $change) {
            $this->audit->record(
                AuditEvent::SETTING_UPDATED,
                description: __('Setting updated: :key', ['key' => $change['key']]),
                properties: $change,
                channel: AuditLogName::SETTINGS,
            );
        }
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
