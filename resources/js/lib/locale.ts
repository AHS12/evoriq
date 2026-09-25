/**
 * The active application locale.
 *
 * The server renders `<html lang>` on every full page load (app.blade.php)
 * and locale switches reload the page, so the attribute always matches the
 * Inertia shared `i18n.locale`. Safe to use from module-scope helpers that
 * cannot call hooks.
 */
export function appLocale(): string {
    return document.documentElement.lang || 'en';
}
