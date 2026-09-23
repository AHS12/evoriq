<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\UserRepositoryInterface;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Show the dashboard shell.
     */
    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => $this->stats(),
            'setup' => $this->setupChecklist(),
            'hasAnalytics' => false,
        ]);
    }

    /**
     * The headline metric cards. Analytics metrics are placeholders until the
     * Clockify sync module lands; the user count is live.
     *
     * @return array<int, array{key: string, label: string, value: string, description: string}>
     */
    private function stats(): array
    {
        return [
            [
                'key' => 'tracked_hours',
                'label' => 'Tracked hours',
                'value' => '—',
                'description' => 'Awaiting Clockify import',
            ],
            [
                'key' => 'billable',
                'label' => 'Billable amount',
                'value' => '—',
                'description' => 'Awaiting Clockify import',
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'value' => (string) $this->users->countAll(),
                'description' => 'Accounts in the workspace',
            ],
            [
                'key' => 'projects',
                'label' => 'Projects',
                'value' => '—',
                'description' => 'Awaiting Clockify import',
            ],
        ];
    }

    /**
     * The onboarding checklist shown until analytics are available.
     *
     * @return array<int, array{key: string, label: string, description: string, status: string}>
     */
    private function setupChecklist(): array
    {
        return [
            [
                'key' => 'connect_clockify',
                'label' => 'Connect Clockify',
                'description' => 'Link your Clockify workspace using an API key.',
                'status' => 'coming_soon',
            ],
            [
                'key' => 'import_history',
                'label' => 'Import history',
                'description' => 'Synchronize historical time entries and projects.',
                'status' => 'coming_soon',
            ],
            [
                'key' => 'view_analytics',
                'label' => 'View analytics',
                'description' => 'Explore reports, comparisons and exports.',
                'status' => 'coming_soon',
            ],
        ];
    }
}
