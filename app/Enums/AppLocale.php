<?php

namespace App\Enums;

/**
 * The locales shipped with the starter kit. Values match the framework
 * translation directories under lang/ and the application dictionaries under
 * lang/app/{locale}.json.
 */
enum AppLocale: string
{
    case EN = 'en';
    case BN = 'bn';
    case FR = 'fr';
    case DE = 'de';
    case ES = 'es';

    /**
     * The locale's name in its own language (locale-independent, so it is
     * never translated).
     */
    public function nativeName(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::BN => 'বাংলা',
            self::FR => 'Français',
            self::DE => 'Deutsch',
            self::ES => 'Español',
        };
    }

    /**
     * Options for select inputs.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $locale): array => [
                'label' => $locale->nativeName(),
                'value' => $locale->value,
            ],
            self::cases(),
        );
    }

    /**
     * The switcher payload shared with the frontend.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public static function shared(): array
    {
        return array_map(
            static fn (self $locale): array => [
                'code' => $locale->value,
                'name' => $locale->nativeName(),
            ],
            self::cases(),
        );
    }
}
