<?php

namespace App\Services\Setting;

use App\DTOs\Setting\NotificationPreferenceDTO;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Enums\UserSettingKey;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Resolves and persists per-user notification preferences.
 *
 * Mirrors AppearanceService: defaults are merged with whatever the user has
 * explicitly stored through `ahs12/laravel-setanjo`.
 */
class NotificationPreferenceService
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    /**
     * The fully resolved preferences for a user (defaults applied).
     *
     * @return array{inapp: bool, sound: bool, desktop: bool, muted_types: array<int, string>, muted_categories: array<int, string>}
     */
    public function forUser(?User $user): array
    {
        return array_merge($this->defaults(), $this->stored($user));
    }

    /**
     * Whether a notification of the given type may be created for the user
     * (PIPE-11). A muted **category** suppresses creation; a muted individual
     * type only suppresses interruption (handled client-side).
     */
    public function allows(?User $user, NotificationType $type): bool
    {
        if ($user === null) {
            return true;
        }

        return ! in_array($type->category()->value, $this->forUser($user)['muted_categories'], true);
    }

    /**
     * Only the preferences explicitly stored for a user.
     *
     * @return array<string, mixed>
     */
    public function stored(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $settings = $user->settings();

        return array_filter(
            [
                'inapp' => $this->toBool($settings->get(UserSettingKey::NOTIFICATION_INAPP_ENABLED->value)),
                'sound' => $this->toBool($settings->get(UserSettingKey::NOTIFICATION_SOUND_ENABLED->value)),
                'desktop' => $this->toBool($settings->get(UserSettingKey::NOTIFICATION_DESKTOP_ENABLED->value)),
                'muted_types' => $this->decodeTypes($settings->get(UserSettingKey::NOTIFICATION_MUTED_TYPES->value)),
                'muted_categories' => $this->decodeCategories($settings->get(UserSettingKey::NOTIFICATION_MUTED_CATEGORIES->value)),
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }

    public function update(User $user, NotificationPreferenceDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto): void {
            $settings = $user->settings();

            $settings->set(UserSettingKey::NOTIFICATION_INAPP_ENABLED->value, $dto->inapp ? '1' : '0');
            $settings->set(UserSettingKey::NOTIFICATION_SOUND_ENABLED->value, $dto->sound ? '1' : '0');
            $settings->set(UserSettingKey::NOTIFICATION_DESKTOP_ENABLED->value, $dto->desktop ? '1' : '0');
            $settings->set(UserSettingKey::NOTIFICATION_MUTED_TYPES->value, json_encode(array_values($dto->mutedTypes)));
            $settings->set(UserSettingKey::NOTIFICATION_MUTED_CATEGORIES->value, json_encode(array_values($dto->mutedCategories)));
        });

        $this->audit->record(
            AuditEvent::NOTIFICATION_PREFERENCES_UPDATED,
            $user,
            [
                'inapp' => $dto->inapp,
                'sound' => $dto->sound,
                'desktop' => $dto->desktop,
                'muted_types' => array_values($dto->mutedTypes),
                'muted_categories' => array_values($dto->mutedCategories),
            ],
            actor: $user,
            channel: AuditLogName::SETTINGS,
        );
    }

    /**
     * @return array{inapp: bool, sound: bool, desktop: bool, muted_types: array<int, string>, muted_categories: array<int, string>}
     */
    public function defaults(): array
    {
        return [
            'inapp' => true,
            'sound' => true,
            'desktop' => false,
            'muted_types' => [],
            'muted_categories' => [],
        ];
    }

    private function toBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @return array<int, string>|null
     */
    private function decodeTypes(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_filter(
            array_map(static fn (mixed $type): string => (string) $type, $decoded),
            static fn (string $type): bool => NotificationType::tryFrom($type) !== null,
        ));
    }

    /**
     * @return array<int, string>|null
     */
    private function decodeCategories(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_filter(
            array_map(static fn (mixed $category): string => (string) $category, $decoded),
            static fn (string $category): bool => NotificationCategory::tryFrom($category) !== null,
        ));
    }
}
