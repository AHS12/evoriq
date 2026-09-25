# Plan — Port i18n, theme polish and role/user detail sheets from `larave-react-starter`

**Status:** Ready to execute (fully validated in a throwaway clone)
**Source:** `K:\Projects\larave-react-starter` — branch `dev`, commits:
- `b1fdfba` — `feat: implement internationalization support across various pages`
- `ef6ad8c` — `feat: add French translations … RoleDetailsSheet and UserDetailsSheet … theme tweaks`
- `5f76ddb` — `feat: enhance setup wizard error handling and improve internationalization documentation`

**Target:** `K:\Projects\Evoriq` (branch `main`, HEAD `640fbf3`)
**Predecessor:** `docs/AUDIT_UX_PORT_PLAN.md` (audit log + data-loading UX, already ported)
**Related:** `AGENTS.md`, `.agents/rules/*`, `.agents/skills/*`

---

## 1. Objective

The starter gained three things since our last port:

1. **Full i18n** — 5 locales (`en`, `bn`, `fr`, `de`, `es`) with an in-house
   translation layer (locale middleware/service, `useTranslation` hook, language
   switcher, per-user + global locale settings, framework translations via
   `laravel-lang`, a parity test and an `add-translation` skill).
2. **New UI** — `RoleDetailsSheet` and `UserDetailsSheet` (row-click detail
   sheets), a language switcher on the public pages, and **setup-wizard error
   handling** that jumps the wizard to the step owning failing fields.
3. **Theme / polish** — fixed dark-mode `--destructive` tokens, dropdown
   destructive styles, a Noto Sans Bengali font fallback, locale-aware login
   redirects, and appearance-save cancellation on locale switch.

Same approach as last time: **cherry-pick the two feature commits** and resolve
a small, known set of conflicts. Everything is validated end-to-end in a
throwaway clone.

---

## 2. Scope — Port / Skip

| Commit | Decision | Why |
| --- | --- | --- |
| `b1fdfba` i18n | **Port** | Core deliverable; validated. |
| `ef6ad8c` FR + role/user sheets + theme | **Port** | Core deliverable; applied with **zero conflicts**. |
| `5f76ddb` setup wizard errors + i18n docs | **Port** | Brings real setup-wizard error handling; its `README.md` changes are skipped (see §5). |
| `76cf7a5` docs/welcome feature card | **Skip** | Starter-only copy: `README.md`, `AGENTS.md` audit blurb and the starter welcome marketing text. No product value for Evoriq. |

Everything else on the starter branch was already handled in the previous port.

---

## 3. Validation evidence (throwaway clone)

| Check | Result |
| --- | --- |
| `git cherry-pick b1fdfba` | Applied; **7 conflicts** (see §5) |
| `git cherry-pick ef6ad8c` | Applied **cleanly, zero conflicts** |
| `git cherry-pick 5f76ddb` | Applied; **1 conflict** in `README.md` only (resolved as ours) |
| `composer install` (adds `laravel-lang/lang`, `laravel-lang/publisher` dev deps) | OK |
| `php artisan test` | **385 passed**, 2969 assertions (was 368; **+17** locale tests) |
| `composer lint:check` (Pint, `lang` excluded) | Passed |
| `composer types:check` (PHPStan/Larastan) | **0 errors** |
| `npm run build` (Wayfinder for `locale.update`) + `tsc` | Passed |
| `npm run check` (vp) | Passed after `vp check --fix` on the 2 hand-merged files |

The only post-merge touch-up was auto-formatting of two hand-edited files
(`setup-checklist.tsx`, `dashboard.tsx`) — `composer check:fix` handles it.

---

## 4. What arrives

### 4.1 i18n infrastructure (new backend)

```
app/Enums/AppLocale.php                              # en|bn|fr|de|es + nativeName()/options()/shared()
app/Services/Setting/LocaleService.php               # stored-for-user / global default / dictionary (en fallback)
app/Http/Middleware/SetLocale.php                    # user pref -> session -> global setting -> config
app/Http/Controllers/Setting/LocaleController.php    # POST /locale (persist + session + toast)
app/Http/Requests/Setting/UpdateLocaleRequest.php    # in: AppLocale values
```

Modified: `app/Enums/SettingKey.php` (`APP_LOCALE`), `app/Enums/UserSettingKey.php`
(`LOCALE`), `app/Enums/UserStatus.php` (labels via `__()`),
`app/Http/Middleware/HandleInertiaRequests.php` (shares `i18n` prop + injects
`LocaleService`), `app/Providers/AppServiceProvider.php`
(`configureTranslations()` → `lang/app` JSON path), `bootstrap/app.php`
(appends `SetLocale`), `routes/web.php` (`POST locale`).

### 4.2 i18n infrastructure (new frontend)

```
resources/js/hooks/use-translation.ts         # t()/tChoice()/locale/supported from shared prop
resources/js/components/app/language-select.tsx   # select (used on welcome)
resources/js/components/app/language-toggle.tsx   # dropdown (used in auth/header)
resources/js/lib/locale.ts                    # appLocale() reads <html lang>
resources/js/types/i18n.ts                    # I18n / SupportedLocale types
```

### 4.3 Translation files

```
lang/app/{en,bn,fr,de,es}.json                # app dictionary (363 keys in en)
lang/{en,bn,fr,de,es}/{auth,pagination,passwords,validation}.php
lang/{en,bn,fr,de,es}.json                    # framework JSON
```

`pint.json` excludes `lang`; `composer.json` gains dev deps
`laravel-lang/lang ^15.37`, `laravel-lang/publisher ^16.8` and a
`post-update-cmd` hook `php artisan lang:update` (network; update-time only).

### 4.4 Translation coverage

The i18n commit wraps strings across ~95 frontend files (all auth pages, setup,
dashboard, roles, users, files, data-processing, audit-logs, settings,
notifications, admin appearance, error page, sidebar, breadcrumbs, layouts) and
backend enum labels (`SettingKey`, `UserStatus`, `NotificationType`, etc.).

### 4.5 New UI (`ef6ad8c`)

```
resources/js/components/role/role-details-sheet.tsx   # role detail sheet (permissions, system badge, edit hook)
resources/js/components/user/user-details-sheet.tsx   # user detail sheet (status, roles, 2FA, edit hook)
```

- `roles/index.tsx` / `users/index.tsx` wire `DataTable`'s `onRowClick` to open
  the sheet; the actions column stops click propagation.
- `LanguageSelect` gains a switching/loading state.
- `use-appearance.tsx` gains `cancelPendingAppearancePersist()` (called before
  the locale-switch reload so a pending PATCH isn't lost).
- `app/Http/Responses/LoginResponse.php` + `TwoFactorLoginResponse.php` +
  `FortifyServiceProvider` bindings: **login/2FA always redirect to the
  dashboard**, ignoring the intended URL.
- `setup/index.tsx` (`5f76ddb`): on a submit attempt, if validation errors
  arrive, the wizard jumps to the step that owns the failing fields
  (`finish`/`admin`), so server-side errors are never shown on a hidden step.
- `app.css`: fixed dark `--destructive` / `--destructive-foreground` tokens and
  added `'Noto Sans Bengali'` to the sans stack.
- `ui/dropdown-menu.tsx`: destructive item now uses `text-destructive`.

### 4.6 Tests & docs

```
tests/Feature/Settings/LocaleSettingsTest.php
tests/Unit/LocaleServiceUnitTest.php          # LocaleService with mocked settings
tests/Unit/TranslationParityTest.php          # every locale must share the en key set
.agents/skills/add-translation/SKILL.md
AGENTS.md  §8.11 Translation-friendly code + skills index entry
```

---

## 5. The 7 conflicts (`b1fdfba`) and their resolutions

All conflicts are the same shape: **Evoriq's product copy vs the starter's
generic copy, where the starter additionally wrapped it in `t()`**. Resolution
principle: keep Evoriq's text, adopt the `t()` wrapper, keep the Evoriq name
and product behaviour.

| File | Resolution |
| --- | --- |
| `app/Enums/SettingKey.php` | Keep `SYSTEM_NAME => 'Evoriq'`; **add** `APP_LOCALE => AppLocale::EN->value` to the `defaultValue()` match. |
| `resources/js/components/dashboard/setup-checklist.tsx` | Wrap Evoriq's line in `t()`: `{t('Finish setting up Evoriq to unlock analytics.')}`. |
| `resources/js/components/notification/notification-preference-form.tsx` | Wrap Evoriq's line in `t()`. **See §6.1 — the merge also imported the starter's auto-persist refactor.** |
| `resources/js/layouts/auth/auth-split-layout.tsx` | Keep `useTranslation()` + `appName = name ?? 'Evoriq'`. |
| `resources/js/pages/dashboard.tsx` | Keep Evoriq's Clockify/analytics card and `hasAnalytics` prop; add `const { t } = useTranslation();` and wrap Evoriq's copy in `t()`. |
| `resources/js/pages/setup/index.tsx` | Wrap Evoriq's heading/description in `t()` (keep "Set up your workspace" and the Clockify description). |
| `resources/js/pages/welcome.tsx` | 4 hunks: keep `appName = name ?? 'Evoriq'`; wrap Evoriq's hero copy, CTA labels and steps heading in `t()` (keep Clockify marketing text). |

`ef6ad8c` needed **no** conflict resolution.

### 5.1 The single `5f76ddb` conflict

`README.md` only (the starter's intro + new "Internationalization" section vs
Evoriq's product README). **Resolution:** keep Evoriq's README and drop the
starter README edits — the commit's `AGENTS.md` (i18n conventions) and
`resources/js/pages/setup/index.tsx` (error handling) hunks merge cleanly and are
the parts worth keeping.

---

## 6. Deliberate deviations & decisions

### 6.1 The notification form silently adopts the skipped cleanup's UX

`ad67293` (skipped in the previous port) rewrote
`notification-preference-form.tsx` from `useForm` + submit to `useState` +
instant PATCH (auto-save, desktop-permission handling). Because `b1fdfba`'s
i18n diff was authored on top of that version, the 3-way merge resolves to the
auto-persist version. **Decision:** accept it — it is a genuine UX improvement
and Evoriq's endpoint already accepts the same PATCH payload — or revert the
form to Evoriq's `useForm` version and re-apply only the `t()` wrapper. Flag in
review; recommended to accept.

### 6.2 A shadcn `ui/` primitive is edited

`ui/dropdown-menu.tsx` is changed by `ef6ad8c`, but `AGENTS.md` §9 says
`resources/js/components/ui/*` must not be edited. The change is a small
destructive-variant colour fix. **Decision:** apply it and note the exception
(or re-publish the component from shadcn). Low impact either way.

### 6.3 Evoriq-specific copy stays English until added to the dictionaries

`t('Evoriq turns your Clockify data …')` returns the English key when no
translation exists. `TranslationParityTest` requires **all five** `lang/app/*.json`
files to share the same key set, so adding Evoriq's product strings to the
dictionaries means adding the key to `en`, `bn`, `fr`, `de`, `es` together.
Follow-up (not blocking): run the `add-translation` skill over Evoriq's
product-specific copy (welcome/dashboard/setup) and translate.

### 6.4 Login always lands on the dashboard

`LoginResponse`/`TwoFactorLoginResponse` change post-login redirect to always go
to `dashboard`, ignoring the intended URL. This is intentional upstream; keep it
unless Evoriq wants deep-link-after-login.

### 6.5 `lang:update` runs on `composer update`

The new `post-update-cmd` fetches framework translations from the network.
Harmless for `composer install`; only runs on update.

### 6.6 A latent duplicate key

`lang/app/en.json` contains both `"Audit log"` and `"Audit Log"` (case variant).
JSON decodes to one; the parity test still passes. Trivial cleanup opportunity.

---

## 7. Execution steps (exact)

Run from `K:\Projects\Evoriq`:

```sh
# 1. Bring the two commits in (temporary remote; remove afterwards)
git remote add starter "K:/Projects/larave-react-starter"
git fetch starter dev
git cherry-pick -x b1fdfba      # resolve the 7 conflicts in §5
git cherry-pick -x ef6ad8c      # applies cleanly
git cherry-pick -x 5f76ddb      # README conflict only -> keep ours
git remote remove starter

# 2. Dependencies (adds the laravel-lang dev deps; composer.lock is ported)
composer install

# 3. Frontend generated helpers + formatting
npm run build                   # Wayfinder for locale.update
composer check:fix              # auto-format the hand-merged files

# 4. Quality gate
composer check
```

`php artisan migrate` is **not** required (no schema change). Setting/permission
syncs are **not** required (`APP_LOCALE` is a new setting key shipped by
`settings:sync`, so run `php artisan settings:sync` once to seed it).

---

## 8. Post-port verification checklist

- [ ] `php artisan settings:sync` seeds `app_locale`; Admin → Settings → General
      shows a Language select.
- [ ] Welcome page + login page show the language switcher; switching locale
      reloads and re-renders in the chosen language.
- [ ] `settings:sync` + login persists `UserSettingKey::LOCALE` per user.
- [ ] `lang/app/*.json` parity test passes (`tests/Unit/TranslationParityTest.php`).
- [ ] Roles and Users tables open the new details sheets on row click; the
      actions menu still works (click does not open the sheet).
- [ ] Setup wizard jumps to the failing step when a submit returns errors.
- [ ] Dark mode destructive buttons/menu items render correctly.
- [ ] `composer check` green.

---

## 9. Risks & mitigations

| Risk | Mitigation |
| --- | --- |
| The i18n cherry-pick drags in parts of the skipped `ad67293` (notification auto-persist) | Called out in §6.1; review that one file and decide. |
| Evoriq product copy remains English in non-`en` locales | Wrap + extend all five dictionaries via the `add-translation` skill (follow-up). |
| Editing a shadcn `ui/` primitive | Deliberate, documented (§6.2). |
| `TranslationParityTest` fails if a key is added to only one dictionary | Add keys to all five files together. |
| Login redirect behaviour change | Intentional; revert the two response classes if not wanted. |

---

## 10. Effort estimate

| Step | Estimate |
| --- | --- |
| Cherry-pick 3 commits + resolve 8 conflicts | 30–45 min |
| `composer install` + `settings:sync` + `check:fix` | 10 min |
| `npm run build` + `composer check` | 15–20 min |
| UI smoke test (language switch, detail sheets, dark mode) | 20–30 min |
| **Total** | **~1.5 hours** |
| Follow-up: translate Evoriq-specific copy (optional) | 1–2 hours |

---

## 11. Progress log

- [x] Researched the starter `dev` branch; identified `b1fdfba` + `ef6ad8c` +
      `5f76ddb` to port and `76cf7a5` to skip.
- [x] Simulated the port in a throwaway clone: 7 conflicts resolved, `ef6ad8c`
      clean, `5f76ddb` with a single README conflict kept as ours; **385 tests**,
      Pint, PHPStan(0), tsc and vp check all green.
- [ ] Execute on `K:\Projects\Evoriq` (§7).
- [ ] Post-port verification (§8).
- [ ] Optional: translate Evoriq-specific copy via `add-translation`.
