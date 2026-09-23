---
name: add-permission
description: 'Declare a new Evoriq permission in the registry, sync it to the database, grant it to system roles and enforce it in a policy with tests.'
argument-hint: 'Permission name, for example: report.export or connection.credentials.update.'
---

# Add Permission

## When to use

When a new module or gated action needs an authorization check. Permissions are
declared in `config/permission-registry.php`, seeded by `PermissionSeeder`, and
upserted with `php artisan permission:sync`. See `AGENTS.md` §7.13.

## Steps

1. **Declare the permission** under its module in
   `config/permission-registry.php`. Each entry needs `name`
   (`{module}.{action}`), `group`, `description`, and optionally `is_system`
   (for settings/role/developer-style permissions that custom roles may not
   receive).

   ```php
   'report' => [
       'permissions' => [
           // ...
           ['name' => 'report.export', 'group' => 'report', 'description' => 'Export reports'],
       ],
   ],
   ```

2. **Grant it to system roles** in `database/seeders/RoleSeeder.php`. Admin
   receives everything except the excluded list; add the permission to the
   Member `whereIn` list only if members should have it.

3. **Sync** to the database (upserts permissions and grants everything to the
   super admin):

   ```sh
   php artisan permission:sync
   ```

4. **Enforce it** in the module policy (`app/Policies/{Model}Policy.php`) using
   `$user->hasPermissionTo('report.export')` (or `hasAnyPermission([...])` when
   any of several grants access), and authorize every controller action with
   `Gate::authorize(...)`.

5. **Cover it with a test** — a granted user is allowed and a user without the
   permission is denied. `tests/Feature/Rbac/PoliciesTest.php` is the template;
   use the `makeUserWithPermissions()` / `member()` helpers.

## Notes

- Reuse the standard five per module (`view`, `view.all`, `create`, `update`,
  `delete`) plus action-specific extras.
- `is_system` permissions are not assignable to custom roles.
- The super admin bypasses every check through `Gate::before` — always test with
  a non-super-admin user.

## Completion checks

- [ ] Registry updated and `php artisan permission:sync` run.
- [ ] Policy enforces the permission and controllers call `Gate::authorize`.
- [ ] Allow + deny tests pass and `composer check` is green.
