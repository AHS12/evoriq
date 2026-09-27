<?php

use App\Models\AuditActivity;
use App\Services\Setup\EnvironmentWriter;
use App\Services\Storage\StorageConfigurator;

beforeEach(function () {
    // The storage settings write to the environment file; in tests this must
    // never be the real .env, so bind a configurator on a temp file.
    $path = sys_get_temp_dir().'/storage-settings-feature-'.uniqid().'.env';

    $this->app->instance(
        StorageConfigurator::class,
        new StorageConfigurator(new EnvironmentWriter($path)),
    );

    $this->envPath = $path;
});

test('the remote storage page renders for users with settings.view', function () {
    $this->actingAs(admin());

    $this->get(route('admin.settings.storage.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/storage')
            ->has('storage')
            ->has('configured'));
});

test('the remote storage page is forbidden without settings.view', function () {
    $this->actingAs(member());

    $this->get(route('admin.settings.storage.edit'))->assertForbidden();
});

test('storage credentials are validated and persisted to the environment file', function () {
    $this->actingAs(superAdmin());

    $this->from(route('admin.settings.storage.edit'))
        ->patch(route('admin.settings.storage.update'), [
            'enabled' => '1',
            'provider' => 'r2',
            'access_key_id' => '',
            'secret_access_key' => 'secret',
            'region' => 'auto',
            'bucket' => 'my-bucket',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'use_path_style' => '1',
        ])
        ->assertSessionHasErrors('access_key_id');

    $this->from(route('admin.settings.storage.edit'))
        ->patch(route('admin.settings.storage.update'), [
            'enabled' => '1',
            'provider' => 'r2',
            'access_key_id' => 'key-123',
            'secret_access_key' => 'secret-123',
            'region' => 'auto',
            'bucket' => 'my-bucket',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'use_path_style' => '1',
        ])
        ->assertRedirect(route('admin.settings.storage.edit'));

    $values = (new EnvironmentWriter($this->envPath))->read();

    expect($values['AWS_ENABLED'])->toBe('true')
        ->and($values['AWS_ACCESS_KEY_ID'])->toBe('key-123')
        ->and($values['AWS_SECRET_ACCESS_KEY'])->toBe('secret-123')
        ->and($values['AWS_BUCKET'])->toBe('my-bucket')
        ->and($values['AWS_USE_PATH_STYLE_ENDPOINT'])->toBe('true')
        // Credential changes are audited without their values.
        ->and(AuditActivity::query()->where('event', 'storage_updated')->exists())->toBeTrue();
});

test('a blank secret keeps the stored one and is never echoed back', function () {
    $this->actingAs(superAdmin());

    $this->patch(route('admin.settings.storage.update'), [
        'enabled' => '1',
        'provider' => 's3',
        'access_key_id' => 'key-123',
        'secret_access_key' => 'secret-123',
        'region' => 'us-east-1',
        'bucket' => 'my-bucket',
        'endpoint' => null,
        'use_path_style' => '0',
    ])->assertRedirect();

    $this->patch(route('admin.settings.storage.update'), [
        'enabled' => '1',
        'provider' => 's3',
        'access_key_id' => 'key-456',
        'secret_access_key' => '',
        'region' => 'us-east-1',
        'bucket' => 'my-bucket',
        'endpoint' => null,
        'use_path_style' => '0',
    ])->assertRedirect();

    $values = (new EnvironmentWriter($this->envPath))->read();

    expect($values['AWS_SECRET_ACCESS_KEY'])->toBe('secret-123')
        ->and($values['AWS_ACCESS_KEY_ID'])->toBe('key-456');

    $this->actingAs(superAdmin())
        ->get(route('admin.settings.storage.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('storage.has_secret', true)
            ->missing('storage.secret_access_key'));
});
