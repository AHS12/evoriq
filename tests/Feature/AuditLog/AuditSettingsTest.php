<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Models\AuditActivity;

test('the audit settings page renders for users with settings.view', function () {
    $this->actingAs(admin());

    $this->get(route('admin.settings.audit.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/audit')
            ->has('group.fields', 5));
});

test('the audit settings page is forbidden without settings.view', function () {
    $this->actingAs(member());

    $this->get(route('admin.settings.audit.edit'))->assertForbidden();
});

test('retention settings are validated and persisted', function () {
    // settings.update is deliberately withheld from the Admin role.
    $this->actingAs(superAdmin());

    $this->from(route('admin.settings.audit.edit'))
        ->patch(route('admin.settings.audit.update'), [
            'audit_retention_auth' => '365',
            'audit_retention_security' => '1825',
            'audit_retention_rbac' => '10', // below the minimum
            'audit_retention_settings' => '365',
            'audit_retention_domain' => '1825',
        ])
        ->assertSessionHasErrors('audit_retention_rbac');

    $this->from(route('admin.settings.audit.edit'))
        ->patch(route('admin.settings.audit.update'), [
            'audit_retention_auth' => '365',
            'audit_retention_security' => '1825',
            'audit_retention_rbac' => '1095',
            'audit_retention_settings' => '365',
            'audit_retention_domain' => '1825',
        ])
        ->assertRedirect(route('admin.settings.audit.edit'));

    expect(Settings::get(SettingKey::AUDIT_RETENTION_SECURITY->value))->toBe('1825')
        ->and(Settings::get(SettingKey::AUDIT_RETENTION_DOMAIN->value))->toBe('1825');

    // Settings changes are audited automatically.
    expect(AuditActivity::query()->where('event', 'setting_updated')->exists())->toBeTrue();
});
