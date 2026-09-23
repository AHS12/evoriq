<?php

namespace App\Enums\Concerns;

trait HasDriverOptions
{
    /**
     * A human-readable label for the driver.
     */
    abstract public function label(): string;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
