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

    case NOTIFICATION_INAPP_ENABLED = 'notifications.inapp';
    case NOTIFICATION_SOUND_ENABLED = 'notifications.sound';
    case NOTIFICATION_DESKTOP_ENABLED = 'notifications.desktop';
    case NOTIFICATION_MUTED_TYPES = 'notifications.muted_types';
}
