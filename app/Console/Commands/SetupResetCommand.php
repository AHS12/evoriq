<?php

namespace App\Console\Commands;

use App\Services\Setup\SetupService;
use Illuminate\Console\Command;

class SetupResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:reset
                            {--fresh : Delete all users so onboarding starts from scratch}
                            {--force : Run even when the application is in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the one-time setup so the onboarding wizard can be run again (development only)';

    /**
     * Execute the console command.
     */
    public function handle(SetupService $setup): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to reset setup in production. Re-run with --force if you are sure.');

            return self::FAILURE;
        }

        $fresh = (bool) $this->option('fresh');

        $result = $setup->reset($fresh);

        $this->info('Setup reset — the onboarding wizard is available again.');

        $this->table(
            ['Install lock removed', 'Setup flag removed', 'Users deleted'],
            [[
                $result['lock_removed'] ? 'yes' : 'no',
                $result['setting_removed'] ? 'yes' : 'no',
                (string) $result['users_deleted'],
            ]],
        );

        if ($result['has_super_admin']) {
            $this->line('Existing super admin kept — the wizard will skip the account step.');
            $this->line('Use --fresh to delete all users and start onboarding from scratch.');
        } else {
            $this->line('No super admin found — onboarding will create one.');
        }

        $this->line(sprintf('Visit %s to start onboarding.', url('/setup')));

        return self::SUCCESS;
    }
}
