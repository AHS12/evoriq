<?php

namespace App\Http\Middleware;

use App\Http\Resources\Notification\NotificationResource;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Notification\NotificationService;
use App\Services\Setting\NotificationPreferenceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        protected NotificationService $notifications,
        protected NotificationPreferenceService $notificationPreferences,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user instanceof User ? $user->getRoleNames()->all() : [],
                'permissions' => $user instanceof User
                    ? $user->getAllPermissions()->pluck('name')->all()
                    : [],
                'isSuperAdmin' => $user instanceof User && $user->isSuperAdmin(),
            ],
            'can' => [
                'developer' => $user instanceof User && $user->hasPermissionTo('developer.view'),
                'file' => $user instanceof User && $user->hasPermissionTo('file.view'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => fn (): array => $this->notificationSummary($request),
            'activeJobs' => fn (): int => $this->activeJobCount($request),
        ];
    }

    /**
     * The number of pending/running jobs the user can see, for the sidebar badge.
     */
    private function activeJobCount(Request $request): int
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return 0;
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $viewAll = $permissions->contains('data-processing.view.all');

        if (! $viewAll && ! $permissions->contains('data-processing.view')) {
            return 0;
        }

        return app(DataProcessingJobService::class)->activeCountFor((int) $user->id, $viewAll);
    }

    /**
     * The bell's shared payload: unread count, latest notifications and the
     * user's preferences. Evaluated lazily, so it never runs for non-Inertia
     * responses (e.g. the SSE stream).
     *
     * @return array{unread_count: int, recent: array<int, mixed>, preferences: array<string, mixed>}
     */
    private function notificationSummary(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [
                'unread_count' => 0,
                'recent' => [],
                'preferences' => $this->notificationPreferences->defaults(),
            ];
        }

        return [
            'unread_count' => $this->notifications->unreadCountFor($user->id),
            'recent' => NotificationResource::collection(
                $this->notifications->recentFor($user->id, (int) config('notification.feed.recent_limit', 8)),
            )->resolve(),
            'preferences' => $this->notificationPreferences->forUser($user),
        ];
    }
}
