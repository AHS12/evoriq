# Translations & internationalization

The app ships with 5 locales — **English (`en`), Bangla (`bn`), French (`fr`),
German (`de`) and Spanish (`es`)** — served through an in-house i18n layer.

## Switching language

- The globe icon in the header dropdown, or **Settings → General → Language**
  (the same control, same behavior).
- Switching persists the preference server-side and does a **full page
  reload**, so the dictionary, `<html lang>` and `Intl` formatting all
  refresh.
- Resolution order: the user's stored preference → session locale (guests) →
  the global default setting (Administration → Settings) → `en`.

## Dictionaries

One file per locale: `lang/app/{locale}.json`. The active dictionary is
delivered to the frontend as a shared Inertia prop; missing keys fall back to
the English text.

## Adding strings

- **English source string = translation key.** Wrap every user-visible string
  in `t()` from `useTranslation()`: `t('Save changes')`. The English text
  renders before a translation exists.
- Never use string concatenation or template interpolation for UI text — use
  Laravel-style `:param` interpolation: `t('Delete :name?', { name })`.
- **Strings inside module-scope helpers** (formatters, utils) cannot call
  hooks — accept a `t: Translate` parameter and pass `t` from the calling
  component.
- **Zod schema messages stay English keys** — `useZodForm` translates client
  errors automatically.
- **Backend labels** (`Enums\*::label()`, `description()`, `options()`) must
  be wrapped in `__()`; the frontend renders them through `t()` again.
- **Static `Page.layout` breadcrumb titles** don't need wrapping — the
  `Breadcrumbs` component translates them at render.

Add every new key to **all five** dictionaries in `lang/app/` —
`tests/Unit/TranslationParityTest.php` fails the build if any locale is
missing one.

## Dates & numbers

Formatted with the `Intl` APIs using the active locale (`appLocale()` from
`resources/js/lib/locale.ts`).

## Bangla transliteration rule

Widely-understood tech terms are phonetically transliterated, not translated —
রোলস, এক্সপোর্ট, ইমপোর্ট, স্ট্যাটাস, ফাইল, ডাউনলোড, ড্যাশবোর্ড, টাইমজোন.
Translate the rest naturally.

## Adding a new locale

1. Copy `lang/app/en.json` to `lang/app/{locale}.json` and translate.
2. Register the locale in the locale configuration/middleware so it appears in
   the switcher.
3. `TranslationParityTest` will enforce that all keys exist going forward.
