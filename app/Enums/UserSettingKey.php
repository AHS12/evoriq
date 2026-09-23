<?php

namespace App\Enums;

/**
 * Per-user settings stored through `ahs12/laravel-setanjo` and scoped to the
 * authenticated user via `$user->settings()`.
 */
enum UserSettingKey: string
{
    case APPEARANCE_MODE = 'appearance.mode';
    case APPEARANCE_THEME = 'appearance.theme';
    case APPEARANCE_ACCENT = 'appearance.accent';
    case APPEARANCE_CONTRAST = 'appearance.contrast';
}
