<?php

namespace App\Services\Setting;

use App\DTOs\Setting\AppearanceDTO;
use App\Enums\AppearanceContrast;
use App\Enums\AppearanceMode;
use App\Enums\AppearanceTheme;
use App\Enums\UserSettingKey;
use App\Helpers\ColorHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AppearanceService
{
    public const DEFAULT_ACCENT = '#3b82f6';

    /**
     * The fully resolved appearance preferences for a user (defaults applied).
     *
     * @return array{mode: string, theme: string, accent: string, contrast: string}
     */
    public function forUser(?User $user): array
    {
        return array_merge($this->defaults(), $this->stored($user));
    }

    /**
     * Only the appearance preferences explicitly stored for a user.
     *
     * @return array<string, string>
     */
    public function stored(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $settings = $user->settings();

        return array_filter(
            [
                'mode' => $this->matchEnum($settings->get(UserSettingKey::APPEARANCE_MODE->value), AppearanceMode::class),
                'theme' => $this->matchEnum($settings->get(UserSettingKey::APPEARANCE_THEME->value), AppearanceTheme::class),
                'accent' => $this->matchAccent($settings->get(UserSettingKey::APPEARANCE_ACCENT->value)),
                'contrast' => $this->matchEnum($settings->get(UserSettingKey::APPEARANCE_CONTRAST->value), AppearanceContrast::class),
            ],
            static fn (?string $value): bool => $value !== null,
        );
    }

    public function update(User $user, AppearanceDTO $dto): void
    {
        $values = [
            UserSettingKey::APPEARANCE_MODE->value => $dto->mode->value,
            UserSettingKey::APPEARANCE_THEME->value => $dto->theme->value,
            UserSettingKey::APPEARANCE_ACCENT->value => $dto->accent,
            UserSettingKey::APPEARANCE_CONTRAST->value => $dto->contrast->value,
        ];

        DB::transaction(function () use ($user, $values): void {
            $settings = $user->settings();

            foreach ($values as $key => $value) {
                $settings->set($key, $value);
            }
        });
    }

    /**
     * A readable foreground color for the given accent.
     */
    public function accentForeground(string $accent): string
    {
        return ColorHelper::contrastForeground($accent);
    }

    /**
     * @return array{mode: string, theme: string, accent: string, contrast: string}
     */
    public function defaults(): array
    {
        return [
            'mode' => AppearanceMode::SYSTEM->value,
            'theme' => AppearanceTheme::DEFAULT->value,
            'accent' => self::DEFAULT_ACCENT,
            'contrast' => AppearanceContrast::NORMAL->value,
        ];
    }

    /**
     * Validate a stored value against its enum, ignoring anything unknown.
     *
     * @param  class-string<\BackedEnum>  $enum
     */
    private function matchEnum(mixed $value, string $enum): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $case = $enum::tryFrom($value);

        return $case instanceof \BackedEnum && is_string($case->value) ? $case->value : null;
    }

    private function matchAccent(mixed $value): ?string
    {
        return is_string($value) && ColorHelper::isHex($value) ? strtolower($value) : null;
    }
}
