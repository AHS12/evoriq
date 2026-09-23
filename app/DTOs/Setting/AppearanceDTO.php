<?php

namespace App\DTOs\Setting;

use App\Enums\AppearanceContrast;
use App\Enums\AppearanceMode;
use App\Enums\AppearanceTheme;
use App\Http\Requests\Setting\UpdateAppearanceRequest;

final readonly class AppearanceDTO
{
    public function __construct(
        public AppearanceMode $mode,
        public AppearanceTheme $theme,
        public string $accent,
        public AppearanceContrast $contrast,
    ) {}

    public static function fromRequest(UpdateAppearanceRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            AppearanceMode::from((string) $validated['mode']),
            AppearanceTheme::from((string) $validated['theme']),
            strtolower((string) $validated['accent']),
            AppearanceContrast::from((string) $validated['contrast']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'theme' => $this->theme->value,
            'accent' => $this->accent,
            'contrast' => $this->contrast->value,
        ];
    }
}
