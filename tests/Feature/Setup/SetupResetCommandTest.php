<?php

namespace Tests\Feature\Setup;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Setup\SetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SetupResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_reset_command_restores_the_onboarding_wizard()
    {
        Storage::fake('local');
        Storage::disk('local')->put(SetupService::LOCK_FILE, now()->toIso8601String());

        Settings::set(SettingKey::SETUP_COMPLETED_AT->value, now()->toIso8601String());

        $setup = app(SetupService::class);

        $this->assertTrue($setup->isComplete());

        $this->artisan('setup:reset')->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists(SetupService::LOCK_FILE));
        $this->assertFalse(Settings::has(SettingKey::SETUP_COMPLETED_AT->value));
        $this->assertTrue(Storage::disk('local')->exists(SetupService::RESET_MARKER));
        $this->assertFalse($setup->isComplete());
    }

    public function test_setup_reset_command_keeps_the_super_admin_account()
    {
        Storage::fake('local');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

        $this->artisan('setup:reset')->assertSuccessful();

        $this->assertTrue(
            $superAdmin->fresh()->hasRole(UserRole::SUPER_ADMIN->value),
        );
    }

    public function test_setup_reset_fresh_option_deletes_every_user()
    {
        Storage::fake('local');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
        User::factory()->count(2)->create();

        $this->artisan('setup:reset', ['--fresh' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertFalse(app(SetupService::class)->hasSuperAdmin());
    }

    public function test_setup_reset_command_redirects_the_app_back_to_the_wizard()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole(UserRole::SUPER_ADMIN->value);

        $this->artisan('setup:reset')->assertSuccessful();

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('setup.index'));
    }
}
