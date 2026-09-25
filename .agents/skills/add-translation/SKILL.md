---
name: add-translation
description: 'Extract user-visible strings from a frontend module or backend enum into the 5-locale app dictionaries (en, bn, fr, de, es), following the translation-friendly code rules in AGENTS.md §8.11.'
argument-hint: 'Module name, for example: Reports or Developer.'
---

# Add Translation

## When to use

When a page, component or backend enum still shows hardcoded English and must
support the 5 app locales. Read `AGENTS.md` §8.11 first — it defines the
conventions this skill applies.

**English source string = translation key.** `t('Save changes')` renders
correctly in English before any translation exists, so extraction is always
safe and can be done module by module.

## Steps

1. **Find the strings.** Grep the module for hardcoded text: JSX text nodes,
   `placeholder="…"`, `title="…"`, `aria-label="…"`, template literals
   (`` `${x} rows` ``), and backend `label()`/`description()`/`options()` in
   `app/Enums/*` (wrap those in `__()`).

2. **Wrap them with `t()`** from `useTranslation()`:

   ```tsx
   const { t } = useTranslation();

   <PageHeader title={t('Files')} description={t('Everything stored here.')} />
   <Input placeholder={t('Search files…')} />
   <Button aria-label={t('Delete :name', { name: file.name })}>
   ```

   - Replace concatenation/interpolation with `:param` interpolation:
     `` `Deleted ${name}` `` → `t('Deleted :name', { name })`.
   - **Module-scope helpers cannot call hooks** — give them a `t: Translate`
     parameter (import the type from
     `components/data-processing/job-utils.ts`) and pass `t` from the calling
     component.
   - Nested/plain helper components (not hooks) need their own
     `useTranslation()` call.
   - Zod schema messages stay English keys — `useZodForm` translates them
     automatically. Do not pass `t` to schemas or forms using it.
   - Static `Page.layout` breadcrumb titles and auth layout
     `title`/`description` need no wrapping — `Breadcrumbs` and
     `AuthSplitLayout` translate them at render.
   - Backend enum labels: wrap with `__()` in PHP. The frontend re-translates
     the returned English label through `t()` (`SettingField`, `SettingsForm`,
     dashboard stats).

3. **Add every new key to all five dictionaries** in
   `lang/app/{en,bn,fr,de,es}.json`, keeping keys sorted:

   ```json
   "No files yet": "এখনো কোনো ফাইল নেই",
   ```

   For speed, merge many keys with a small Node script that reads each file,
   adds missing keys, sorts and rewrites — then verify with the parity test.

4. **Bangla (`bn`) transliteration rule** — widely-understood tech terms are
   phonetically transliterated, not translated:

   - রোলস, ইউজার, এক্সপোর্ট, ইমপোর্ট, স্ট্যাটাস, ফাইল, ডাউনলোড, আপলোড,
     প্রিভিউ, সার্চ, সেটিংস, ড্যাশবোর্ড, টাইমজোন, প্রোফাইল, জব
   - Translate everything else naturally (e.g. "Delete file?" → ফাইল মুছবেন?).
   - Dates/numbers stay `Intl`-driven — never hardcode formatted text.

5. **Verify:**

   ```sh
   php artisan test tests/Unit/TranslationParityTest.php   # all 5 locales share keys
   npm run types:check                                      # t() typing
   npm run build
   ```

   The parity test fails the build if any locale is missing a key, so an
   incomplete translation batch cannot be merged silently.

## Gotchas

- Rebuilt bundles are stale after extraction — run `npm run build` (or use the
  dev server) and hard-refresh once.
- Locale switching does a **full page reload** (`POST /locale` →
  `window.location.reload()`). Never switch locale with a soft Inertia visit:
  `appLocale()` reads `<html lang>`, which is only set on a full load, so dates
  would keep the old locale.
- Do not translate: emails/URLs, timezone identifiers, format names
  (`CSV`, `XLSX`), brand names, or log/error identifiers that feed tests.
