<?php

namespace App\DTOs\Setting;

use Illuminate\Foundation\Http\FormRequest;

final readonly class NotificationPreferenceDTO
{
    /**
     * @param  array<int, string>  $mutedTypes
     */
    public function __construct(
        public bool $inapp = true,
        public bool $sound = true,
        public bool $desktop = false,
        public array $mutedTypes = [],
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        /** @var array<int, mixed> $muted */
        $muted = (array) ($data['muted_types'] ?? []);

        return new self(
            inapp: (bool) ($data['inapp'] ?? true),
            sound: (bool) ($data['sound'] ?? true),
            desktop: (bool) ($data['desktop'] ?? false),
            mutedTypes: array_values(array_map(static fn (mixed $type): string => (string) $type, $muted)),
        );
    }
}
