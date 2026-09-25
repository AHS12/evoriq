# Plan — Evoriq UI Build-out

**Status:** Draft for review
**Scope:** First-run setup, user management, roles & permissions, settings,
developer tools, shared UI foundation, and the application shell.
**Stack:** Laravel 13 · Inertia 3 · React 19 · TypeScript · Tailwind 4 · shadcn/ui
**Related:** `TDR.md`, `AGENTS.md`, `docs/FRC_STARTER_PORT_PLAN.md`,
`.agents/rules/*`, `.agents/skills/*`
**Source starter kit:** the official Laravel React starter kit already in the
repo (`resources/js/layouts`, `components/ui`, Fortify auth).

---

## 1. Objective

The backend "starter DNA" has been ported (`FRC_STARTER_PORT_PLAN.md` §14:
RBAC, queues, exports, developer tools, settings, media, testing harness). What
is missing is the **product UI**: a real first-run experience, administration
screens, and a shared component layer that makes every future screen consistent
and fast to build.

This plan delivers, in order:

1. A **shared UI foundation** (design system + reusable admin components).
2. A **one-time first-run Setup Wizard** that creates the super admin (`id=1`).
3. **User management** (create + email credentials/invite, edit, roles, status,
   delete) for super admins/admins.
4. **Roles & permissions** management (role CRUD + permission matrix).
5. **Global settings** management.
6. A polished **Developer Tools** area and a real **Dashboard shell**.
7. Auth/onboarding **polish** and removal of public self-registration.

**Non-goals (this plan):** Clockify connection/sync UI, analytics dashboards,
report builder, exports UI beyond what exists. Those depend on the not-yet-built
Clockify sync modules and get their own plan. The shell and components built here
are designed to host them.

---

## 2. Current-state audit

### 2.1 What already exists

| Area | State | Key files |
| --- | --- | --- |
| Auth (login, reset, verify, 2FA, passkeys) | Complete | `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `resources/js/pages/auth/*` |
| Layouts | Starter kit (sidebar, header, auth, settings) | `resources/js/layouts/**` |
| shadcn primitives | ~24 components | `resources/js/components/ui/*` |
| Theme (light/dark/system) | Complete | `app.css`, `hooks/use-appearance.tsx`, `pages/settings/appearance.tsx` |
| RBAC data + policies | Complete (39 perms, 3 roles) | `config/permission-registry.php`, `app/Policies/*`, `database/seeders/*` |
| Developer tools page | Basic cards + health | `app/Http/Controllers/Developer/DeveloperController.php`, `pages/developer/index.tsx` |
| Exports | Backend + JSON endpoints | `app/Http/Controllers/Export/ExportController.php`, `routes/exports.php` |
| Uploads | Backend + simple page | `pages/uploads/index.tsx`, `routes/uploads.php` |
| Settings storage | `ahs12/laravel-setanjo`, 2 keys | `app/Enums/SettingKey.php`, `database/seeders/SettingSeeder.php` |
| Tests | Pest, seeded `$seed = true`, helpers | `tests/Pest.php`, `tests/TestCase.php` |

### 2.2 What is missing (the gap this plan closes)

| Gap | Detail |
| --- | --- |
| **First-run setup** | No wizard, no "installed" gate, no super-admin creation flow. Public registration is currently wide open (`Features::registration()`). |
| **User management** | No `UserRepository/Service/Controller/Resource`, no routes, no UI. Policy exists but nothing calls it. |
| **Role & permission management** | No repository/service/controller/UI. Policies exist. |
| **Global settings UI** | Only 2 settings keys, no service/controller/UI. |
| **Shared admin components** | No `table`, `data-table`, `switch`, `tabs`, `alert-dialog`, `popover`, `textarea`; no page header, empty state, confirm dialog, stat card. |
| **`can` props** | Only `developer` and `upload` are shared (`HandleInertiaRequests.php:47`). |
| **Navigation** | Flat sidebar with Dashboard/Uploads/Developer only (`app-sidebar.tsx`). |
| **Real dashboard** | Placeholder pattern only (`pages/dashboard.tsx`). |
| **Email** | No user-invitation notification; local mailer is `log` (mailpit not wired); no runtime SMTP configuration. |

---

## 3. Guiding principles

1. **Follow the starter kit, don't fight it.** Reuse `AppLayout`, shadcn
   primitives, Wayfinder, `<Form>`, `InputError`, `Spinner`, `useFlashToast`.
2. **Server-driven, typed props.** Every screen gets data from a controller
   prop; no ad-hoc fetching (`frontend-architecture.md`).
3. **Service–Repository backend.** Every new module follows the exact recipe in
   `.agents/skills/generate-module` (DTO → repository+interface → service →
   request → resource → controller → routes → tests).
4. **Permission-first UI.** Every navigation item and action is gated by a
   permission; the frontend mirrors backend policies via shared `can` props.
5. **Industry-standard UX.** Clear page headers, consistent spacing, skeleton
   loading, empty states, destructive-action confirmations, optimistic toasts,
   keyboard accessible, dark mode on every surface.
6. **Consistency over cleverness.** One `DataTable`, one `PageHeader`, one
   `ConfirmDialog`, reused everywhere.
7. **Security by default.** No secrets to the browser; credentials via signed,
   expiring links; setup is one-time and lockable.

---

## 4. Information architecture

Replace the flat sidebar with grouped, permission-gated navigation.

```text
Evoriq
├── Overview
│   └── Dashboard                     (always)
├── Analytics                         (future — placeholders hidden until built)
│   ├── Reports                       (report.view)
│   └── Exports                       (export.view)
├── Workspace                         (future)
│   ├── Connections                   (connection.view)
│   └── Sync activity                 (sync.view)
└── Administration
    ├── Users                         (user.view.all)
    ├── Roles & Permissions           (role.view.all)
    └── Settings                      (settings.view)
        ├── General
        ├── Mail                      (settings.update)
        └── Developer                 (developer.view)
```

- Sidebar renders a group only when at least one child is permitted.
- `nav-main.tsx` is extended to accept grouped items (`NavGroup[]`).
- Each admin section gets a consistent `AdministrationLayout` (section nav on
  the left, like the existing `settings/layout.tsx`) so Users/Roles/Settings
  feel like one area.
- **The former top-level "Developer" sidebar item moves inside Settings**
  (Settings → Developer). Developer tools are a settings concern, not a
  first-class product area. Personal settings (Profile/Security/Appearance)
  stay under the user menu.
- **Files replaces Uploads.** The `Upload` module is repurposed into a **Files**
  area — a modern file browser for user files and generated reports/exports
  (see §4.2). Permissions are renamed `upload.*` → `file.*`.
- **Two "Settings" entries exist today** — Administration → Settings and the
  user-menu Settings (Profile/Security/Appearance). Confusing; tracked in §4.3.

### 4.1 Settings area (design)

The starter kit's plain settings nav is not enough. The Administration →
Settings area gets a dedicated, modern layout:

- Left section nav (General · Mail · Developer) with icons, active state and
  descriptions; content in cards with clear section headers.
- **General** — workspace name, timezone, date/week format.
- **Mail** — mailer, host, port, credentials (masked), encryption, from
  address/name, plus a "Send test email" action (§9.4).
- **Developer** — tool cards (Telescope/Pulse/Horizon), live health checks,
  queue/failed-job counts, scheduler next-runs and gated maintenance actions
  (clear cache, queue:restart) — the current `developer/index` page, relocated.
- Every field/action gated by `settings.view` / `settings.update` /
  `developer.view`.

### 4.2 Files area (design)

A single place for everything the user can download or manage, replacing the
`Uploads` screen.

```text
Files
├── Files     — user-uploaded files (media library)
└── Reports   — generated export files (DataProcessingJob)
```

- **Sources:** two tabs. `files` = user uploads (`Upload` model + medialibrary);
  `reports` = generated export artifacts (`DataProcessingJob`). Each list is
  server-paginated; a `source` query param selects the tab.
- **Row actions:** preview (images/PDF inline), download, copy link, delete.
- **Filters:** search, type, date; server-driven via the shared `DataTable`.
- **Upload:** drag-and-drop + file picker (media library).
- **Storage:** keep `spatie/laravel-medialibrary`; generated export files stay
  on the disk from `config/exports.php`.
- **Permissions:** `file.view`, `file.view.all`, `file.create`, `file.delete`
  (renamed from `upload.*`); reports reuse `export.view`.
- **Route:** `/files`, page `files/index`.

A fully unified single list (both sources interleaved) is deferred — the tabbed
model avoids cross-table pagination and keeps each source's permissions.

### 4.3 Naming — two "Settings" entries (to fix)

The sidebar has **Administration → Settings** (General/Mail/Developer) while the
user menu also shows **Settings** (Profile/Security/Appearance). Same label, two
destinations.

**Fix (planned):** rename the user-menu entry to **Account** (destination
unchanged); Administration keeps **Settings** (workspace/system scope). Deferred
to the polish phase (§11) to avoid churn while the admin area grows.

---

## 5. Phase 1 — Shared UI foundation

> Goal: a small, high-leverage component layer so every later screen is
> assembly, not invention.

### 5.1 Dependencies

```sh
# shadcn primitives (generate, do not hand-write)
npx shadcn@latest add table textarea switch tabs alert-dialog popover command
# data table engine
npm install @tanstack/react-table
```

`components.json` already targets `@/components/ui` (new-york, neutral). Keep
generated files untouched (`AGENTS.md` §9).

### 5.2 New UI primitives

| File | Purpose |
| --- | --- |
| `components/ui/table.tsx` | shadcn table primitives |
| `components/ui/textarea.tsx` | multi-line inputs |
| `components/ui/switch.tsx` | boolean settings / toggles |
| `components/ui/tabs.tsx` | section tabs (role editor, settings) |
| `components/ui/alert-dialog.tsx` | destructive confirmations |
| `components/ui/popover.tsx` | filter popovers, API-usage popover later |
| `components/ui/command.tsx` | command palette / combobox (search) |

### 5.3 New shared application components

All under `resources/js/components/app/` (feature-agnostic, named exports).

| Component | Responsibility |
| --- | --- |
| `page-header.tsx` | Title + description + right-side actions slot; used on every page |
| `section-header.tsx` | Smaller in-card section headings |
| `data-table.tsx` | Generic server-driven table over `@tanstack/react-table` (`manualPagination`/`manualSorting`) |
| `data-table-toolbar.tsx` | Search input + filter slots + view actions |
| `data-table-pagination.tsx` | Prev/next + page size + "x of y" |
| `data-table-column-header.tsx` | Sortable column header with sort indicator |
| `empty-state.tsx` | Icon + title + description + action (no data, no results) |
| `confirm-dialog.tsx` | `AlertDialog` wrapper for delete/disable actions |
| `stat-card.tsx` | KPI card (label, value, delta, icon) for dashboard |
| `can.tsx` | `<Can permission="user.create">…</Can>` render gate |
| `copy-button.tsx` | Copy-to-clipboard with toast (invite links, IDs) |
| `theme-toggle.tsx` | Compact light/dark/system toggle (appearance exists as tabs) |
| `form-field.tsx` | Label + control + `InputError` composition (optional sugar) |

### 5.4 Hooks

| Hook | Responsibility |
| --- | --- |
| `hooks/use-can.ts` | Read shared `auth.permissions`; `can(permission)` helper |
| `hooks/use-data-table-filters.ts` | Sync table search/sort/page to Inertia query params (debounced `router.get`, `preserveState`) |
| `hooks/use-confirm.tsx` | Imperative confirm dialog helper (optional) |

### 5.5 Types

`resources/js/types/`:

- `pagination.ts` — `Paginated<T>` (`data`, `links`, `meta`) matching Laravel
  paginator output.
- `user.ts` — `User`, `UserStatus`, `UserRole`.
- `role.ts` — `Role`, `Permission`.
- `setting.ts` — `SettingGroup`, `SettingField`.
- Extend `auth.ts` — `Auth.permissions: string[]`, `Auth.roles: string[]`;
  widen `Can`.

### 5.6 Shared props (backend)

Extend `app/Http/Middleware/HandleInertiaRequests.php` `share()`:

```php
'auth' => [
    'user' => $user,
    'roles' => $user?->getRoleNames() ?? [],
    'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
],
```

Keep the existing `can` convenience object for the sidebar, but generate it
from a single helper so it stays in sync. This makes `<Can>` and `use-can`
trivial and keeps policies and UI aligned.

### 5.7 Design tokens

`app.css` already defines the full token set and dark mode. Phase 1 does **not**
change the palette; it adds:

- A documented spacing/typography scale usage note (page padding `p-4`/`p-6`,
  card radius `rounded-xl`, headings via `PageHeader`).
- `chart-1..5` are already present for the future analytics charts.
- A brand accent pass is optional and deferred to the polish phase.

### 5.8 Acceptance criteria (Phase 1)

- `npm run build`, `npm run types:check`, `npm run check` pass.
- A demo route or Storybook-free preview is **not** required; components are
  proven by the first real screen (Setup Wizard, Phase 2).
- No `any`, no hard-coded URLs, dark mode verified.

---

## 6. Phase 2 — First-run Setup Wizard

> Goal: on a fresh install, any request redirects to `/setup`; the operator
> creates the one and only super admin (`id=1`); the app locks and never shows
> setup again.

### 6.1 Flow

```text
Fresh request
  └─ RedirectIfSetupRequired (web middleware)
        └─ SetupService::isComplete()? ── no ──> redirect /setup
                                         └─ yes ─> continue

/setup  (multi-step, full-screen split layout)
  1. Welcome + requirements (PHP, extensions, storage writable)
  2. Application     (name, URL, timezone)
  3. Database        (connection test; skip if already migrated)
  4. Super Admin     (name, email, password + confirmation)
  5. Finish          -> create user id=1 + Super Admin role
                     -> mark email verified
                     -> write lock + settings
                     -> auto-login -> /dashboard
```

### 6.2 "Installed" detection

`SetupService::isComplete()` returns `true` when **either**:

1. the lock file exists (`storage/app/private/installed`), **or**
2. a user with the `Super Admin` role already exists.

Reason for (2): local/testing run `migrate --seed`, which seeds
`superadmin@evoriq.test` (`UserSeeder.php`). Setup must be considered complete
there, otherwise every test would be redirected. This also protects against a
lost lock file.

`markInstalled()` writes the lock file and sets `SettingKey::SETUP_COMPLETED_AT`.

### 6.3 Backend

| Layer | File |
| --- | --- |
| Middleware | `app/Http/Middleware/RedirectIfSetupRequired.php` (append to `web` group in `bootstrap/app.php`) |
| Middleware | `app/Http/Middleware/EnsureSetupIncomplete.php` (guard `/setup` routes) |
| Controller | `app/Http/Controllers/Setup/SetupController.php` (`show`, `store`) |
| Request | `app/Http/Requests/Setup/StoreSuperAdminRequest.php` |
| Service | `app/Services/Setup/SetupService.php` (`isComplete`, `requirements`, `complete`) |
| DTO | `app/DTOs/Setup/SetupDTO.php` |
| Enum | extend `SettingKey` with `SETUP_COMPLETED_AT` |
| Routes | `routes/setup.php` (require from `routes/web.php`) |

`SetupService::complete()` runs in `DB::transaction`:

1. `UserRepository::create(...)` (via `UserRepositoryInterface`), then assign
   the `Super Admin` role and `markEmailAsVerified()`.
2. Persist app name/timezone settings.
3. `markInstalled()`.

`show` returns `Inertia::render('setup/index', [...])` including
`requirements` results and current defaults.

**Optional mail step:** the wizard may include a skippable "Email" step so the
operator can enter SMTP credentials up front (needed before the first invite).
If skipped, mail is configured later under Administration → Settings → Mail
(§9.4), and invitation sending surfaces a clear "configure mail" warning.

### 6.4 Disable public registration

- Remove `Features::registration()` from `config/fortify.php`.
- Delete/neutralise `resources/js/pages/auth/register.tsx` usage and the
  register Wayfinder route references; update `welcome.tsx`.
- Update `tests/Feature/Auth/RegistrationTest.php` → replace with setup tests.

### 6.5 Frontend

- `resources/js/pages/setup/index.tsx` — stepper (custom, small) using `Tabs`
  or a local `Step` state; each step a component under
  `resources/js/components/setup/`:
  `step-welcome.tsx`, `step-application.tsx`, `step-database.tsx`,
  `step-admin.tsx`, `step-finish.tsx`, `setup-stepper.tsx`.
- Uses `AuthSplitLayout` for the brand panel; `<Form {...store.form()}>` for the
  final submit.
- New page layout mapping in `app.tsx`: `name.startsWith('setup/')` → `null`
  (page renders its own full-screen layout).

### 6.6 Acceptance criteria

- Fresh DB: visiting `/` or `/dashboard` redirects to `/setup`.
- Completing setup creates exactly one user, with `id=1`, Super Admin role,
  verified email; logs them in; redirects to `/dashboard`.
- **The setup super admin never hits email verification:** `email_verified_at`
  is set at creation, so the `verified` middleware on `/dashboard` passes
  immediately. (See §7.1 for invited users.)
- `/setup` afterwards → 404/redirect to dashboard; lock file exists.
- Seeded installs (tests) never see setup.
- Feature tests: `SetupWizardTest`, `SetupServiceUnitTest`.

---

## 7. Phase 3 — User management

> Goal: super admins/admins create users, assign roles, and users receive a
> secure email to set their password.

### 7.1 Invitation model (recommended)

**Decision locked:** create the user **without** a usable password and send a
**signed, expiring link** so they set their own password on first visit. This is
current industry best practice (no plaintext or generated credentials in email).

```text
Admin creates user (name, email, roles)
   └─ User created (random unusable password, email_verified_at = null, status=invited)
   └─ UserInvitation notification -> signed URL (7 days)
        GET /invitations/{user}/accept?signature=...
          └─ InvitationController@show   (set-password form, email prefilled)
          └─ InvitationController@store  (set password, mark email verified, login)
```

**Email verification for invited users:** the invite link already proves the
user controls the inbox, so accepting it sets `email_verified_at = now()`.
Invited users therefore do **not** receive a separate verification email and are
never blocked by the `verified` middleware — the same end result as the setup
super admin. The starter kit's `Features::emailVerification()` stays enabled for
any future self-service flow, but in the normal admin-invites-user flow it is
effectively auto-satisfied.

If an invite expires, the admin uses "Resend invitation", which issues a new
signed link and invalidates the old one.

### 7.2 Backend module

| Layer | File |
| --- | --- |
| Enum | `app/Enums/UserStatus.php` (`invited`, `active`, `suspended`) + migration column |
| DTO | `app/DTOs/User/UserDTO.php`, `app/DTOs/User/UserFilterDTO.php` |
| Repository | `app/Repositories/Contracts/UserRepositoryInterface.php`, `app/Repositories/User/UserRepository.php` |
| Service | `app/Services/User/UserService.php` |
| Requests | `app/Http/Requests/User/StoreUserRequest.php`, `UpdateUserRequest.php`, `AssignRolesRequest.php`, `SetPasswordRequest.php` |
| Resource | `app/Http/Resources/User/UserResource.php` (roles, status, avatar) |
| Controller | `app/Http/Controllers/User/UserController.php` |
| Controller | `app/Http/Controllers/User/InvitationController.php` (accept) |
| Notification | `app/Notifications/UserInvitation.php` |
| Policy | reuse `app/Policies/UserPolicy.php` (already complete) |
| Routes | `routes/users.php` (`auth`,`verified`; `user.*`) + invitation routes (guest) |

`UserRepository` (per `generate-module`):

- `buildFilterQuery(UserFilterDTO)` — search (name/email), `role`, `status`.
- `paginate`, `findById`, `create`, `update`, `delete`, `assignRoles`.
- `create`/`update` take arrays; service passes `$dto->toArray()`.

`UserService`:

- `create(UserDTO)` → transaction: create, assign roles, send invitation.
- `update`, `assignRoles`, `activate`/`suspend`, `resendInvitation`,
  `sendPasswordReset`, `delete` (refuses super admin — mirrors `UserPolicy`).
- Ownership/audit (`created_by`/`updated_by`) derived from `auth()->id()`.

Register the binding in `RepositoryServiceProvider`.

### 7.3 Routes & permissions

```text
GET    /users                 user.view.all    -> index
GET    /users/create          user.create      -> create
POST   /users                 user.create      -> store
GET    /users/{user}/edit     user.update      -> edit
PATCH  /users/{user}          user.update      -> update
DELETE /users/{user}          user.delete      -> destroy
POST   /users/{user}/roles    user.update      -> assignRoles
POST   /users/{user}/invite   user.create      -> resendInvitation
POST   /users/{user}/suspend  user.update      -> toggleStatus
GET    /invitations/{user}/accept   guest      -> invitation.accept
POST   /invitations/{user}          guest      -> invitation.store
```

### 7.4 Frontend

- `pages/users/index.tsx` — `PageHeader` + `DataTable` (name/email avatar, roles
  badges, status badge, created, actions menu: Edit, Resend invite, Send
  password reset, Suspend/Activate, Delete with `ConfirmDialog`). Server-driven
  search/role/status filters via `use-data-table-filters`.
- **Create and edit happen in a `Dialog`** on the list page
  (`components/user/user-form-dialog.tsx` wrapping
  `components/user/user-form.tsx`) — a modern dialog pattern, not dedicated
  pages. Invite is automatic on create.
- `pages/auth/accept-invitation.tsx` — set-password form (auth layout).
- Feature components under `components/user/`: `user-form.tsx`,
  `user-form-dialog.tsx`, `role-select.tsx`, `user-status-badge.tsx`,
  `user-row-actions.tsx`.
- Add "Users" to the Administration nav (gated `user.view.all`).

### 7.5 Acceptance criteria

- Creating a user sends one `UserInvitation` notification (asserted via
  `Notification::fake()`), user has no usable password until accept.
- Accepting the link sets the password, verifies email, and logs in.
- Cannot delete/suspend the super admin; cannot assign `Super Admin` unless
  actor is super admin (policy already enforces).
- Tests: `UserFeatureTest`, `UserServiceUnitTest`, `InvitationFeatureTest`.

---

## 8. Phase 4 — Roles & permissions

> Goal: manage roles and assign permissions through a clear matrix.

### 8.1 Backend module

| Layer | File |
| --- | --- |
| DTO | `app/DTOs/Role/RoleDTO.php`, `RoleFilterDTO.php` |
| Repository | `RoleRepositoryInterface`, `app/Repositories/Role/RoleRepository.php` |
| Service | `app/Services/Role/RoleService.php` |
| Requests | `StoreRoleRequest`, `UpdateRoleRequest`, `SyncRolePermissionsRequest` |
| Resource | `app/Http/Resources/Role/RoleResource.php`, `PermissionResource.php` |
| Controller | `app/Http/Controllers/Role/RoleController.php`, `PermissionController.php` (read-only catalog) |
| Routes | `routes/roles.php` (`role.*`, `permission.view` for catalog) |

`RoleService` rules (adapt `frc-backend` `RoleService`, minus tenancy):

- System roles (`is_system = true`) cannot be renamed or deleted
  (`RolePolicy` already returns false for delete; enforce in service too).
- Custom roles cannot receive `is_system` permissions (filter them out).
- `syncPermissions` wrapped in a transaction; forget the permission cache via
  `PermissionRegistrar`.
- Cannot remove your own `Super Admin` role / lock yourself out.

### 8.2 Permissions catalog

- Source of truth is `config/permission-registry.php` + `PermissionRegistry`.
- `PermissionController@index` returns permissions grouped by `group` for the
  matrix, including `is_system` so the UI can lock them.
- Read-only in the UI — permissions are developer-defined, synced with
  `php artisan permission:sync` (never created ad hoc from the UI). This keeps
  the registry authoritative (`AGENTS.md` §7.13).

### 8.3 Frontend

- `pages/roles/index.tsx` — `DataTable` of roles: name, `is_system` badge,
  permission count, users count, actions (Edit, Delete).
- `pages/roles/edit.tsx` (also used for create) — role name field + permission
  matrix:
  - Rows grouped by `group` (User, Role, Connection, …), collapsible groups.
  - Header actions: "Select all in group", "Select all", "Clear".
  - System permissions render disabled with a lock tooltip.
  - System roles: name disabled, matrix read-only.
- Components under `components/role/`: `permission-matrix.tsx`,
  `permission-group.tsx`, `role-form.tsx`, `system-role-badge.tsx`.
- Nav entry "Roles & Permissions" gated `role.view.all`.

### 8.4 Acceptance criteria

- Create/edit/delete custom roles; permission sync persists and is reflected
  after `permission:sync`.
- System roles and system permissions are protected in both API and UI.
- Tests: `RoleFeatureTest`, `RoleServiceUnitTest`.

---

## 9. Phase 5 — Global settings

> Goal: typed, grouped global settings (single-tenant) editable in the UI.

### 9.1 Backend

| Layer | File |
| --- | --- |
| Enum | extend `app/Enums/SettingKey.php` with `group`, `type`, `defaultValue`, `description`, `rules()` |
| DTO | `app/DTOs/Setting/SettingDTO.php` (name→value map) |
| Service | `app/Services/Setting/SettingService.php` (typed read, transactional update) |
| Request | `app/Http/Requests/Setting/UpdateSettingsRequest.php` (rules derived from enum) |
| Resource | `app/Http/Resources/Setting/SettingResource.php` (metadata + value) |
| Controller | `app/Http/Controllers/Setting/SettingController.php` (`edit`, `update`) |
| Routes | add `settings/general` to `routes/settings.php` (`settings.view`/`settings.update`) |

Proposed keys (extend beyond current `system_name`, `export_cleanup_days`):

```text
general:  system_name, system_url, timezone, date_format, week_start
exports:  export_cleanup_days, export_format_default
mail:     mail_mailer, mail_host, mail_port, mail_username,
          mail_password (secret), mail_encryption,
          mail_from_address, mail_from_name
```

The enum gains `group()`, `type()`, `rules()` and `isSecret()`; `type()` drives
both validation and the input control (string → `Input`, integer → `Input
number`, boolean → `Switch`, enum → `Select`).

**Secrets:** `ahs12/laravel-setanjo` stores values as plain `longText`, so
anything marked `isSecret()` (the SMTP password) is encrypted with
`Crypt::encryptString()` by `SettingService` before persisting and decrypted on
read. Secrets are **never** sent to the browser — the resource returns
`has_password: true` and a masked placeholder; submitting a blank password keeps
the existing one.

### 9.2 Frontend

- `pages/settings/general.tsx` — grouped `Card` sections rendered from a
  `SettingField[]` prop; controls chosen by `type`; `<Form>` submit; toast on
  success.
- Extend `layouts/settings/layout.tsx` nav with "General" (gated) — or place it
  in the Administration area. **Decision (§13):** keep personal Settings
  (Profile/Security/Appearance) separate from Administration → put General
  Settings under Administration.
- `components/setting/setting-field.tsx` (type → control switch).

### 9.3 Acceptance criteria

- Values persist via `Settings` facade and reload correctly; validation comes
  from the enum; `settings:sync` picks up new keys.
- Tests: `SettingFeatureTest`, `SettingServiceUnitTest`.

### 9.4 Mail / SMTP configuration (settings-driven)

Users must be able to plug in **their own mail provider** (SMTP) from the UI
instead of relying on `.env`. The app reads mail settings from the database at
runtime and falls back to `.env` when unset.

**Runtime wiring — `app/Providers/MailSettingsServiceProvider.php`:**

- Registered in `bootstrap/providers.php`.
- In `boot()`, if the app is installed and the `settings` table exists, merge
  DB values into `config('mail.*')`:
  `mail.default`, `mail.mailers.smtp.{host,port,username,password,encryption}`
  and `mail.from.{address,name}`.
- Guarded with `Schema::hasTable('settings')` + `SetupService::isComplete()` and
  wrapped in `try/catch` so it never breaks `migrate`/`config:cache` on a fresh
  install. When a `mail_mailer` setting is absent, `.env` defaults win.
- The `MailManager` resolves transports lazily, so setting config in `boot()` is
  sufficient; no custom mailer class is required.

**Test-send action:** `POST /settings/mail/test` (`settings.update`) sends a
test message to the current admin's email and returns success/failure to the
UI (`Send test email` button + result toast). This validates credentials before
the operator saves and walks away.

**Caching / workers:** `setanjo` caching is disabled by default; if enabled in
production, `SettingService` must forget the settings cache on write. Long-lived
queue workers read config at process boot, so changing mail settings should be
followed by a `queue:restart` (the developer tools page exposes this in §10.1).

**Local development — switch to mailpit (EnvKit):** EnvKit runs mailpit with
SMTP on **1025** and UI on **8025**. Update `.env` and `.env.example`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@evoriq.test"
MAIL_FROM_NAME="${APP_NAME}"
```

Inspect delivered mail at `http://127.0.0.1:8025`. Tests keep
`MAIL_MAILER=array` (`phpunit.xml`) and assert with `Notification::fake()` /
`Mail::fake()`.

**UI:** `pages/settings/mail.tsx` (or a "Mail" tab in General Settings) with
fields for mailer (smtp/log), host, port, username, password (masked),
encryption (tls/ssl/none), from address and from name, plus the test-send
button. Gated by `settings.view` / `settings.update`.

---

## 10. Phase 6 — Developer tools & dashboard shell

### 10.1 Developer tools (enhance existing)

- Add cards: queue depth / failed jobs (`QueueRegistry` channels), scheduler
  next runs, cache/route/config status, app versions (PHP, Laravel, Node),
  environment, Telescope/Pulse/Horizon (Horizon hidden on Windows — already
  handled).
- Gated maintenance actions (super admin only, `ConfirmDialog`): clear cache,
  clear config/route/view caches, run `settings:sync` / `permission:sync`
  (queued or direct). Each action maps to a controller action with
  `Gate::authorize` and audit logging.
- Components: `components/developer/tool-card.tsx`, `health-list.tsx`,
  `maintenance-actions.tsx`.

### 10.2 Dashboard shell

The real analytics dashboard depends on Clockify sync (not built). For now:

- Replace `pages/dashboard.tsx` placeholders with:
  - A **Setup checklist** card (Connect Clockify → Import history → View
    analytics) driven by future flags, rendered as "coming soon" where needed.
  - Four `StatCard`s (Tracked hours, Billable, Users, Projects) fed by props
    that default to `0`/`—` until the analytics module exists.
  - `EmptyState` for charts with a "Connect Clockify" CTA.
- The controller passes typed props so wiring analytics later is a
  controller-only change.

### 10.3 Acceptance criteria

- Developer page shows live queue/health data and gated maintenance actions
  work; non-permitted users get 403.
- Dashboard renders without errors on an empty database and is ready for real
  metrics.

---

## 11. Phase 7 — Auth & polish

- **Landing page**: replace starter `welcome.tsx` with a clean product landing
  (hero, value props, Login CTA); when authenticated, `/` redirects to
  `/dashboard`. Gated by "is setup complete".
- **Auth pages**: visual polish pass over login/forgot/reset/verify/2FA/passkeys
  using the split layout and consistent branding.
- **Profile/Security/Appearance**: align with new `PageHeader`/card patterns;
  add avatar upload via the existing `Upload`/media module.
- **Global UX**: 404/500 error pages, `Skeleton` loading states, consistent
  `sonner` toasts (already wired via `useFlashToast`), mobile pass on all new
  screens.

---

## 12. Backend work summary (all phases)

| Module | Model | Migration | DTO | Repo | Service | Request | Resource | Controller | Routes | Policy | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Setup | – | – | ✓ | (User) | ✓ | ✓ | – | ✓ | ✓ | – | ✓ |
| User | exists | status col | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | exists | ✓ |
| Invitation | – | token? | – | – | ✓ | ✓ | – | ✓ | ✓ | – | ✓ |
| Role | exists | – | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | exists | ✓ |
| Permission | exists | – | – | – | – | – | ✓ | ✓ | ✓ | exists | ✓ |
| Setting | – | – | ✓ | – | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ |
| Mail | – | – | – | – | ✓ | ✓ | – | ✓ | ✓ | – | ✓ |
| Developer | – | – | – | – | – | – | – | ✓ | exists | ✓ | ✓ |

Bindings added to `RepositoryServiceProvider`: `UserRepositoryInterface`,
`RoleRepositoryInterface`. New permissions (if any) declared in
`config/permission-registry.php` and synced with `permission:sync`
(`.agents/skills/add-permission`).

---

## 13. Open decisions (need product confirmation)

| # | Decision | Recommendation |
| --- | --- | --- |
| 1 | **User onboarding**: signed invite link vs emailed generated credentials | **Decided — signed invite link** (user sets their own password on first visit). |
| 2 | **Invitation expiry / resend** | 7-day expiry, resend invalidates the previous link. |
| 9 | **Mail settings source**: DB settings vs `.env` only | **Decided — DB settings with `.env` fallback**; SMTP password encrypted; test-send action. |
| 10 | **Local mailer** | **Decided — mailpit** (EnvKit: SMTP `1025`, UI `8025`). |
| 3 | **User status model** | `invited` / `active` / `suspended` (soft) vs hard delete only. Recommend both: suspend + delete. |
| 4 | **Where global Settings live** | Administration section (separate from personal Settings). |
| 5 | **Setup wizard steps** | Include DB connection test, or assume `.env` configured? Recommend showing it read-only/skippable. |
| 6 | **Landing page** | Public marketing page vs redirect `/` → login/dashboard. Recommend a minimal public landing. |
| 7 | **Developer maintenance actions** | Allow in-app cache/optimize actions (super admin only) or read-only dashboard? Recommend read-only by default, actions behind a config flag. |
| 8 | **Branding** | Keep neutral palette vs apply an Evoriq accent. Recommend a light accent pass in Phase 7. |

---

## 14. Testing & quality gate

- Every service gets a unit test (Mockery on the repository interface);
  every controller gets a feature test (`RefreshDatabase`, seeded).
  `AGENTS.md` §7.12.
- Add `tests/Mock/UserMockData.php`, `RoleMockData.php`, `SettingMockData.php`.
- RBAC allow/deny tests for each new policy action (`tests/Feature/Rbac`).
- Setup tests must prove the one-time lock and idempotency.
- Notification tests with `Notification::fake()`; mail rendering snapshot for
  `UserInvitation`.
- Required before "done": `composer check` (Pint, Larastan, Pest, vp, tsc) and
  `npm run build` (regenerate Wayfinder after adding routes).

---

## 15. Risks & mitigations

| Risk | Mitigation |
| --- | --- |
| Setup redirect breaks existing tests (seeded super admin) | `isComplete()` also true when a Super Admin exists; `$seed = true`. |
| Registration removal breaks auth tests | Replace `RegistrationTest` with setup tests in the same phase. |
| Permission drift between UI and policies | Share `auth.permissions`; drive `<Can>` and sidebar from it; policies remain authoritative. |
| `DataTable` over-engineered | Keep it server-driven and generic; add features only when a second screen needs them. |
| Email deliverability in local dev | Switch `.env` to EnvKit mailpit (SMTP `1025`, UI `8025`); assert via `Notification::fake()` in tests. |
| SMTP password stored in plaintext by `setanjo` | Encrypt `isSecret()` values with `Crypt` in `SettingService`; never return secrets to the browser. |
| Mail config changes not picked up by running queue workers | Read settings in a boot provider; document/queue `queue:restart` after changes (exposed in Developer Tools). |
| DB mail settings read before migrations run | Guard provider with `Schema::hasTable` + `try/catch`; fall back to `.env`. |
| Scope creep into Clockify analytics | Explicit non-goal; dashboard is a shell with typed props only. |
| Lock file lost | DB fallback (`Super Admin` exists / `SETUP_COMPLETED_AT` setting). |

---

## 16. Phased roadmap & estimates

| Phase | Deliverable | Estimate |
| --- | --- | --- |
| 1 | Shared UI foundation (components, hooks, types, nav) | 2–3 days |
| 2 | First-run Setup Wizard + disable registration | 2–3 days |
| 3 | User management + invitations | 3–4 days |
| 4 | Roles & permissions | 2–3 days |
| 5 | Global settings + SMTP/mailpit wiring | 2–3 days |
| 6 | Developer tools + dashboard shell | 2 days |
| 7 | Auth & polish (landing, error pages, responsive) | 2–3 days |
| **Total** | | **~15–21 days** |

Phases 1→2→3→4 are the critical path (setup must precede user management;
users need roles). Phases 5–7 can run in parallel once Phase 1 lands.

---

## 17. Appendix — file map (new/changed)

### Backend (new)

```text
app/Enums/UserStatus.php
app/Enums/SettingKey.php                         (extend)
app/DTOs/Setup/SetupDTO.php
app/DTOs/User/UserDTO.php
app/DTOs/User/UserFilterDTO.php
app/DTOs/Role/RoleDTO.php
app/DTOs/Role/RoleFilterDTO.php
app/DTOs/Setting/SettingDTO.php
app/Repositories/Contracts/UserRepositoryInterface.php
app/Repositories/Contracts/RoleRepositoryInterface.php
app/Repositories/User/UserRepository.php
app/Repositories/Role/RoleRepository.php
app/Services/Setup/SetupService.php
app/Services/User/UserService.php
app/Services/Role/RoleService.php
app/Services/Setting/SettingService.php
app/Http/Middleware/RedirectIfSetupRequired.php
app/Http/Middleware/EnsureSetupIncomplete.php
app/Http/Requests/Setup/StoreSuperAdminRequest.php
app/Http/Requests/User/{StoreUserRequest,UpdateUserRequest,AssignRolesRequest,SetPasswordRequest}.php
app/Http/Requests/Role/{StoreRoleRequest,UpdateRoleRequest,SyncRolePermissionsRequest}.php
app/Http/Requests/Setting/UpdateSettingsRequest.php
app/Http/Resources/User/UserResource.php
app/Http/Resources/Role/RoleResource.php
app/Http/Resources/Permission/PermissionResource.php
app/Http/Resources/Setting/SettingResource.php
app/Http/Controllers/Setup/SetupController.php
app/Http/Controllers/User/UserController.php
app/Http/Controllers/User/InvitationController.php
app/Http/Controllers/Role/RoleController.php
app/Http/Controllers/Role/PermissionController.php
app/Http/Controllers/Setting/SettingController.php
app/Http/Controllers/Setting/MailSettingController.php
app/Http/Requests/Setting/SendTestMailRequest.php
app/Providers/MailSettingsServiceProvider.php
app/Notifications/UserInvitation.php
routes/setup.php
routes/users.php
routes/roles.php
```

### Backend (changed)

```text
app/Http/Middleware/HandleInertiaRequests.php    (share roles/permissions)
app/Providers/RepositoryServiceProvider.php      (bindings)
app/Providers/AuthServiceProvider.php            (invitation guest? policies already mapped)
bootstrap/app.php                                (append RedirectIfSetupRequired)
bootstrap/providers.php                          (register MailSettingsServiceProvider)
config/fortify.php                               (remove registration)
config/permission-registry.php                   (any new permissions)
routes/web.php, routes/settings.php              (includes)
database/migrations/*_add_status_to_users_table.php
database/seeders/SettingSeeder.php               (new keys)
.env, .env.example                               (mailpit SMTP 1025)
```

### Frontend (new)

```text
resources/js/components/app/{page-header,section-header,data-table,data-table-toolbar,data-table-pagination,data-table-column-header,empty-state,confirm-dialog,stat-card,can,copy-button,theme-toggle}.tsx
resources/js/components/setup/*.tsx
resources/js/components/user/*.tsx
resources/js/components/role/*.tsx
resources/js/components/setting/setting-field.tsx
resources/js/components/setting/mail-form.tsx
resources/js/components/developer/{tool-card,health-list,maintenance-actions}.tsx
resources/js/hooks/{use-can.ts,use-data-table-filters.ts,use-confirm.tsx}
resources/js/types/{pagination,user,role,setting}.ts
resources/js/pages/setup/index.tsx
resources/js/pages/users/{index,create,edit}.tsx
resources/js/pages/roles/{index,edit}.tsx
resources/js/pages/settings/general.tsx
resources/js/pages/settings/mail.tsx
resources/js/pages/auth/accept-invitation.tsx
```

### Frontend (changed)

```text
resources/js/components/app-sidebar.tsx          (grouped, permission-gated nav)
resources/js/components/nav-main.tsx             (support NavGroup)
resources/js/app.tsx                             (setup/ layout mapping)
resources/js/types/{auth.ts,index.ts}
resources/js/pages/dashboard.tsx                 (real shell)
resources/js/pages/welcome.tsx                   (landing)
resources/js/layouts/settings/layout.tsx         (nav alignment)
```

---

## 18. Definition of done (per phase)

1. Backend follows the Service–Repository recipe; every query in a repository,
   every write in a service transaction.
2. Every action authorized with `Gate::authorize` / policy; UI mirrors via
   shared `can`/`<Can>`.
3. Types are strict (no `any`), Wayfinder-only URLs, dark mode verified,
   accessible labels/focus.
4. Unit + feature tests added; `composer check` and `npm run build` green.
5. No generated files (`actions/`, `routes/`, `components/ui/*`) edited by hand.

---

## 19. Progress log

- [x] **Phase 1 — Shared UI foundation**
      shadcn primitives `table`, `textarea`, `switch`, `tabs`, `alert-dialog`,
      `popover` (+ `@tanstack/react-table`), normalized to the repo convention
      (`@/lib/utils`, individual `@radix-ui/*`). Shared components
      (`PageHeader`, `SectionHeader`, `EmptyState`, `StatCard`, `ConfirmDialog`,
      `Can`, `CopyButton`, `DataTable` suite), hooks (`use-can`,
      `use-data-table-filters`), types (`user`, `role`, `setting`, `pagination`,
      `NavGroup`) and a grouped permission-gated sidebar. `auth.roles` /
      `auth.permissions` shared from `HandleInertiaRequests`. `composer check`
      green.
- [x] **Phase 2 — First-run setup wizard**
      `SettingKey::SETUP_COMPLETED_AT`, `UserRepository(+interface)` binding,
      `SetupService`/`SetupDTO`, `RedirectIfSetupRequired` +
      `EnsureSetupIncomplete` middleware (priority-ordered before `auth`),
      `SetupController`/`StoreSuperAdminRequest`/`routes/setup.php`, Fortify
      public registration removed, and the 3-step wizard UI
      (`pages/setup/index.tsx` + stepper/requirements components). The wizard
      creates the super admin, verifies their email, auto-logs in and writes the
      install lock. Tests: `SetupWizardTest`, `SetupServiceUnitTest`.
      `composer check` green, **104 tests**.
- [x] **Post-Phase-2 cleanup** (see §20)
- [x] **Settings area + Developer relocation** (Phase 5/6 pulled forward)
      `SettingKey` expanded (groups/labels/types/rules/secrets) and
      `SettingService` (encrypted secrets) + `SettingsServiceProvider` (applies
      general + mail config at runtime, `.env` fallback). `SettingController` /
      `MailSettingController` + `TestMail`; routes `admin/settings/{general,mail,developer}`;
      `AdminLayout` with grouped nav; Developer moved into Settings; sidebar
      updated; local mail switched to mailpit (SMTP 1025). Tests:
      `SettingFeatureTest`, `SettingServiceUnitTest`, `DeveloperPageTest`.
      `composer check` green, **113 tests**.
- [x] **Files area** — `Upload` repurposed into a Files browser
      Permissions renamed `upload.*` → `file.*` (registry, RoleSeeder,
      `UploadPolicy`, shared `can.file`); `/files` route + `FileController`
      (files + reports sources); tabbed `files/index` page with grid/list
      toggle, drag-and-drop upload, search/type filters, download/delete, and a
      reports table; sidebar "Uploads" → "Files". Deletes require a confirmation
      dialog; a preview dialog renders images inline, PDFs in an iframe, and a
      type-aware placeholder icon for everything else (`FileThumbnail` /
      `FilePreviewDialog` / `file-utils`); both lists are paginated
      (`DataTablePagination`). Internal `Upload` model kept (a `File` rename
      risks colliding with `Illuminate\Support\Facades\File`). Local DB
      permissions re-synced and old `upload.*` pruned.
      `composer check` green, **113 tests**.
- [x] **Naming fix** — user-menu "Settings" renamed to "Account" (§4.3);
      "Settings" now only means the Administration area.
- [x] **Phase 3 — User management**
      `UserStatus` enum (`invited`/`active`/`suspended`) + migration
      (`status`, `invitation_token`, `invitation_sent_at`, audit `created_by`/
      `updated_by`); `UserDTO`/`UserFilterDTO`; `UserRepository` extended
      (`paginate`/`buildFilterQuery` via `EloquentFilterHelper`); `UserService`
      (create, update, assignRoles, updateStatus, resendInvitation,
      sendPasswordReset, acceptInvitation, delete — with super-admin guards);
      `UserInvitation` notification + signed 7-day link (token rotates on
      resend to invalidate the old one); `InvitationController` (set-password,
      verify email, sign in); `UserController` (index/create/edit/update/delete/
      roles/invite/suspend/password-reset); `UserResource`; requests
      (`StoreUserRequest`, `UpdateUserRequest`, `AssignRolesRequest`,
      `SetPasswordRequest`) with a `ValidatesRoleAssignment` concern that blocks
      non-super-admins from granting `Super Admin`; `EnsureUserIsActive`
      middleware signs suspended users out; `routes/users.php`. Frontend:
      `pages/users/index.tsx` with create/edit in a **dialog**
      (`components/user/user-form-dialog.tsx` + `user-form.tsx`) —
      `components/user/*` (`RoleSelect`, `UserStatusBadge`, `UserRowActions`),
      `pages/auth/accept-invitation.tsx`, Users nav item. Tests:
      `UserFeatureTest`, `InvitationFeatureTest`, `UserServiceUnitTest`.
      `composer check` green, **138 tests**.
- [x] **UI conventions** — theme switcher moved to the top-right of the app
      header (`components/app/theme-toggle.tsx`, mounted in
      `app-sidebar-header.tsx`) per §8.5; create/update forms use dialogs by
      default (§8.4). Client-side form validation uses **Zod** through
      `useZodForm` (`resources/js/hooks/use-zod-form.ts`) + per-form schemas in
      `resources/js/lib/schemas/*`, mirroring the Laravel `FormRequest`
      (server-side source of truth). All recorded in `AGENTS.md`.
- [x] **Queued email** — `UserInvitation`, `TestMail` and a new
      `ResetPasswordNotification` implement `ShouldQueue`; invitations are
      dispatched after the DB transaction commits; the admin "send password
      reset" uses the queued notification via `User::sendPasswordResetNotification`.
      `composer run dev` now listens on all channels and `composer run queue`
      was added. Tests assert mail/notifications are queued on `default`.
- [x] **Phase 4 — Roles & permissions**
      Backend module following the Service–Repository recipe: `RoleDTO` /
      `RoleFilterDTO`, `RoleRepositoryInterface` / `RoleRepository` (search,
      sortable `permissions_count` / `users_count`, permission catalog, system
      permission filtering), `RoleService` (create / update / delete inside
      transactions, `PermissionRegistrar` cache flush), `StoreRoleRequest` /
      `UpdateRoleRequest`, `RoleResource` / `PermissionResource`,
      `RoleController`, `routes/roles.php` (`roles.*`). Rules: system roles
      cannot be renamed or deleted; the **Super Admin** role is fully locked;
      **Admin** / **Member** keep their names but their permission matrix is
      editable; custom roles cannot receive `is_system` permissions; a non
      super admin cannot strip their own `role.update`. Frontend:
      `pages/roles/index.tsx` (`DataTable` + search + pagination) with
      create/edit in a **dialog** (`RoleFormDialog` + `RoleForm`) hosting a
      collapsible, grouped `PermissionMatrix` (select-all per group / all /
      clear) plus `SystemRoleBadge` and `RoleRowActions`; a "Roles &
      Permissions" sidebar entry gated by `role.view.all`. The read-only
      permission catalog is passed as a typed prop gated by `role.view.all`
      instead of a separate `PermissionController`, and the standalone
      `SyncRolePermissionsRequest` was dropped in favour of the combined update
      payload (dialogs by default per §8.4). Tests: `RoleFeatureTest`,
      `RoleServiceUnitTest`. `composer check` green, **161 tests**.
- [x] **Phase 6 — Developer tools & dashboard shell**
      Developer tools now show runtime & cache status, health checks and the
      tool cards (refactored into
      `components/developer/{tool-card,health-list,maintenance-actions}`).
      Super-admin maintenance actions (clear cache / config / route / view,
      sync settings & permissions) and a real **maintenance-mode toggle**
      (`php artisan down` / `up`) are gated by the new `manageDeveloperTools`
      gate and the `developer.maintenance_actions` config flag
      (`DEVELOPER_MAINTENANCE_ACTIONS`, off by default), with a confirm dialog
      and an audit log entry. The developer route is excluded from
      `PreventRequestsDuringMaintenance` so it stays reachable while the app is
      down. Queue depth and scheduler next-runs were dropped in favour of
      Telescope/Pulse. The dashboard is now a real shell backed by
      `DashboardController`: four `StatCard`s (live user count; Clockify
      metrics as placeholders), a "Get started" `SetupChecklist` and an
      `EmptyState` chart area with a Connect Clockify CTA. Tests:
      `DashboardControllerTest`, `DeveloperPageTest` (maintenance gating +
      mode), `DeveloperServiceUnitTest`. `composer check` green, **176 tests**.
- [x] **Phase 7 — Auth & polish**
      **Landing page:** `HomeController` renders `welcome` for guests and
      redirects authenticated users to `/dashboard`; `pages/welcome.tsx` is a
      real product landing (hero, feature grid, three-step "how it works",
      footer, theme toggle, dark mode). Tests: `LandingPageTest`.
      **Error pages:** `pages/errors/error.tsx` (branded full-screen page for
      403/404/419/429/500/503, mapped to `layout = null`) wired through
      `$exceptions->respond` in `bootstrap/app.php`; JSON/API requests keep
      their JSON errors. Tests: `ErrorPageTest`.
      **Auth polish:** `AuthLayout` now uses `auth-split-layout` with a branded
      dark panel (tagline + feature bullets), a theme toggle and a responsive
      single-column fallback on mobile.
      **Account area:** personal settings renamed **Account**
      (`layouts/settings/layout.tsx` uses `PageHeader`, horizontal scrollable
      nav on small screens); Profile, Security, Appearance, Delete account,
      two-factor and passkeys all render as `Card` sections.
      **Avatar upload:** `User` implements `HasMedia` (single-file `profile`
      collection + `thumb` conversion, appended `avatar` accessor),
      `ProfileController@update` accepts an image and the new
      `destroyAvatar` action removes it (`profile.avatar.destroy`);
      `components/profile/avatar-form.tsx` uploads on select and offers
      "Remove". Tests: 3 added to `ProfileUpdateTest`.
      `composer check` green, **185 tests**.
- [x] **Phase 7 follow-up fixes**
      **Avatar upload:** moved to a dedicated `POST settings/profile/avatar`
      endpoint (`profile.avatar.update`, `ProfileAvatarRequest`) — the previous
      combined profile PATCH failed because the file-only request omitted the
      required `name`/`email` and silently 422'd.
      **Auth pages when signed in:** `AuthLayout` renders authenticated pages
      (password confirmation, email verification) inside the app shell; the
      split layout is guest-only (kept for the future public reset flow).
      **Super admin protection:** `ProfileController@destroy` refuses to delete
      the super admin (redirect + error toast) and the Delete account card is
      hidden; `auth.isSuperAdmin` is now shared.
      **Appearance:** moved out of Account into Administration → Settings
      (`admin/settings/appearance`, `settings.view`); removed from the personal
      settings nav and the old `settings/appearance` route/page.
      `composer check` green, **186 tests**.
- [x] **Dev tooling — `setup:reset`**
      `php artisan setup:reset` re-arms the onboarding wizard for local
      testing: clears the install lock and forgets `SETUP_COMPLETED_AT`, then
      writes a reset marker (`SetupService::RESET_MARKER`) so `isComplete()`
      returns false **without** touching the existing Super Admin. The wizard
      detects the existing admin (`hasSuperAdmin` prop) and skips the account
      step, reusing that user (`SetupService::complete`). `--fresh` deletes every
      user (`UserRepository::deleteAll`) so onboarding starts from scratch and
      creates a new super admin. Refuses to run in production without `--force`.
      Also exposed as the **Re-open setup wizard** maintenance action on
      Administration → Settings → Developer (maintenance actions now surface
      non-zero exits as an error toast via `MaintenanceActionFailedException`).
      Tests: `SetupResetCommandTest`, `SetupWizardTest`, `SetupServiceUnitTest`.
      `composer check` green, **193 tests**.

---

## 20. Post-Phase-2 cleanup

Issues found while manually testing the setup wizard and the developer tools.

### 20.1 Pulse `__PHP_Incomplete_Class` crash — fixed

Laravel 13 introduced `cache.serializable_classes => false`, which blocks the
`Collection` / `stdClass` / `CarbonImmutable` instances Pulse caches for its
dashboard cards, producing:

> The script tried to call a method on an incomplete object … `Illuminate\Support\Collection` …

Fix (`config/cache.php`): whitelist exactly the classes Pulse needs —

```php
'serializable_classes' => [
    Illuminate\Support\Collection::class,
    stdClass::class,
    Carbon\CarbonImmutable::class,
],
```

(References: laravel/pulse #503, #505.)

### 20.2 Missing scheduled commands — fixed

`routes/console.php` only scheduled the export cleanup. Added:

```php
Schedule::command('health:check')->everyFiveMinutes();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();
Schedule::command('pulse:check')->everyMinute();
```

Without these, spatie/laravel-health never stores results (and `ScheduleCheck`
reports the scheduler as dead), and Pulse's server metrics stay empty.

### 20.3 Health check scoping — fixed

`HealthServiceProvider`:

- `UsedDiskSpaceCheck` uses `df`, which does not exist on Windows → it crashed.
  Now registered only when `PHP_OS_FAMILY !== 'Windows'`.
- Production-only checks (`EnvironmentCheck`, `DebugModeCheck`,
  `OptimizedAppCheck`, `HorizonCheck`, `RedisCheck`) now run only outside
  `local`/`testing`, so the developer page is not full of expected "failures".

### 20.4 Settings / Developer IA — done

Developer tools now live under Administration → Settings → Developer
(`admin/settings/developer`); the standalone Developer route/page/sidebar entry
are gone. A new `AdminLayout` provides the grouped settings nav
(General · Mail · Developer), and `SettingsServiceProvider` applies general +
mail settings to the runtime config with `.env` fallback. Local mail now points
at EnvKit mailpit (SMTP `1025`, UI `8025`).

### 20.5 Upload → Files (decision: repurpose)

The standalone `Upload` module is repurposed into the **Files** area described in
§4.2: a modern file browser for user files and generated reports. The `upload.*`
permissions are renamed to `file.*` and the `Uploads` sidebar entry becomes
`Files`.

### 20.6 PostgreSQL "too many clients" — mitigated

EnvKit runs a **single PostgreSQL instance shared by every project** (akaunting,
bbm-backend, frc-backend, ims-*, mem-backend, evoriq) with the default
`max_connections = 100`. Evoriq contributed heavily because `SESSION_DRIVER` and
`CACHE_STORE` were `database`, so every request read/wrote the `sessions` table
and hit Postgres for cache, on top of Telescope/Pulse/health writes.

**Fix:** `SESSION_DRIVER` and `CACHE_STORE` now use `redis`, and the `setanjo`
settings cache is enabled (`SETANJO_CACHE_ENABLED=true`) — Postgres holds app
data only. If the limit is hit again, terminate idle backends
(`SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE state='idle' AND pid <> pg_backend_pid();`)
or raise `max_connections` in the EnvKit PostgreSQL config.
