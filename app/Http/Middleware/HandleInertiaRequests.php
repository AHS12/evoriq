<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Notification\NotificationService;
use App\Services\Setting\LocaleService;
use App\Services\Setting\NotificationPreferenceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        protected NotificationService $notifications,
        protected NotificationPreferenceService $notificationPreferences,
        protected LocaleService $locale,
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
            'i18n' => [
                'locale' => fn (): string => app()->getLocale(),
                'fallbackLocale' => fn (): string => (string) config('app.fallback_locale', 'en'),
                'translations' => fn (): array => $this->locale->dictionary(app()->getLocale()),
                'supported' => AppLocale::shared(),
            ],
            'notifications' => fn (): array => $this->notificationSummary($request),
            'activeJobs' => fn (): int => $this->activeJobCount($request),
            'pipeline_revision' => fn (): string => $this->pipelineRevision($request),
        ];
    }

    /**
     * The number of pending/running jobs the user can see, for the sidebar badge.
     */
    private function activeJobCount(Request $request): int
    {
        $scope = $this->dataProcessingScope($request);

        if ($scope === null) {
            return 0;
        }

        return app(DataProcessingJobService::class)->activeCountFor($scope['userId'], $scope['viewAll']);
    }

    /**
     * A derived revision of the visible job rows, polled by `useLivePoll` so the
     * client can skip re-animating when nothing changed.
     */
    private function pipelineRevision(Request $request): string
    {
        $scope = $this->dataProcessingScope($request);

        if ($scope === null) {
            return '';
        }

        return app(DataProcessingJobService::class)->pipelineRevisionFor($scope['userId'], $scope['viewAll']);
    }

    /**
     * The user id + visibility scope for data-processing props, or null when the
     * user may not see jobs at all.
     *
     * @return array{userId: int, viewAll: bool}|null
     */
    private function dataProcessingScope(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $viewAll = $permissions->contains('data-processing.view.all');

        if (! $viewAll && ! $permissions->contains('data-processing.view')) {
            return null;
        }

        return ['userId' => (int) $user->id, 'viewAll' => $viewAll];
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
