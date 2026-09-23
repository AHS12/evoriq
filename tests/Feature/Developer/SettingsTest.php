<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Models\DataProcessingJob;

test('syncs the default settings', function () {
    Settings::forget(SettingKey::SYSTEM_NAME->value);
    Settings::forget(SettingKey::EXPORT_CLEANUP_DAYS->value);

    $this->artisan('settings:sync')->assertSuccessful();

    expect(Settings::get(SettingKey::SYSTEM_NAME->value))->toBe('Evoriq')
        ->and(Settings::get(SettingKey::EXPORT_CLEANUP_DAYS->value))->toBe(7);
});

test('persists setting changes', function () {
    Settings::set(SettingKey::EXPORT_CLEANUP_DAYS->value, 30);

    expect(Settings::get(SettingKey::EXPORT_CLEANUP_DAYS->value))->toBe(30);
});

test('cleanup uses the configured retention setting', function () {
    Settings::set(SettingKey::EXPORT_CLEANUP_DAYS->value, 7);

    $old = DataProcessingJob::factory()->completed()->create([
        'completed_at' => now()->subDays(30),
    ]);

    $this->artisan('data-processing:cleanup-completed')->assertSuccessful();

    $this->assertDatabaseMissing('data_processing_jobs', ['id' => $old->id]);
});
