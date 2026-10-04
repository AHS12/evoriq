# ENT-09 — Custom fields

- **Status:** Done
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00
- **Blocks:** ENT-10, ENT-11, ANA-* (custom dimensions)
- **TDR:** §25.12

## 1. Why

Custom fields define the metadata dimensions users care about (department,
cost center, billing codes…). We must sync their definitions and option sets to
interpret the values attached to entries and users.

## 2. Scope
**In:** `clockify_custom_fields` from `GET /custom-fields`.
**Out:** values (ENT-10/11).

## 3. Data model
```text
clockify_custom_fields
  id, organization_id, workspace_id, clockify_id,
  name, description null, type,
  entity_type,                    # TIMEENTRY | USER
  status,                         # VISIBLE | INVISIBLE | INACTIVE
  required bool default false, only_admin_can_edit bool default false,
  allowed_values json null, placeholder null, workspace_default_value json null,
  project_default_values json null,
  created_at/updated_at/synced_at, raw_data, deleted_at
  unique (organization_id, workspace_id, clockify_id)
```

## 4. Backend
- `CustomFieldSyncHandler` (reference phase): paginate `GET /custom-fields`; map
  `type` (TEXT/NUMBER/LINK/SWITCH/DROPDOWN/DROPDOWN_MULTIPLE…) and
  `entityType`; store `allowedValues` for Select fields.
- Deletion: soft (values referencing it remain for history).

## 5. Frontend / UI
- Custom-field definitions enable metadata grouping in reports (later).

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via analytics/report metadata.

## 7. Acceptance criteria
- [x] Field definitions (type, entity type, allowed values) sync correctly.
- [x] Re-sync idempotent; status changes reflect.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `CustomFieldSyncHandlerTest` (`Http::fake()`): types, allowed values,
  paging.

## 9. Notes & open questions
- Type enum values are partially documented; keep an unknown fallback.
- **Implemented notes:**
  - `clockify_custom_fields` (soft-deletable) + `ClockifyCustomField` model/factory.
    `type`/`entity_type`/`status` are stored as **strings** (Clockify's enum set
    is only partially documented) rather than enums, with `TEXT`/`TIMEENTRY`
    fallbacks — the "unknown fallback" the spec calls for.
  - `CustomFieldSyncHandler` (reference phase) paginates
    `GET /workspaces/{ws}/custom-fields`; maps `allowedValues` (Select fields),
    `workspaceDefaultValue`/`projectDefaultValues` (JSON scalars or objects),
    `placeholder`, `required`, `onlyAdminCanEdit`. `ClockifyCustomField` casts
    the JSON columns so scalars and objects both round-trip.
  - Registered `CUSTOM_FIELDS => CustomFieldSyncHandler` in `config/clockify.php`;
    entitle value specs (ENT-10/11) build on this definition table.
