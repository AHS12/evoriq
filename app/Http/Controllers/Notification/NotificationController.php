<?php

namespace App\Http\Controllers\Notification;

use App\DTOs\Notification\NotificationFeedFilterDTO;
use App\Enums\NotificationPriority;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $service,
    ) {}

    /**
     * Show the full notification feed.
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->getKey();
        $filters = NotificationFeedFilterDTO::fromRequest($request);

        return Inertia::render('notifications/index', [
            'feed' => NotificationResource::collection(
                $this->service->feedFor($userId, $filters),
            ),
            'filters' => $filters->toArray(),
            'priorities' => $this->priorityOptions(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Notification $notification): RedirectResponse
    {
        $this->service->markRead($notification, (int) auth()->id());

        return back();
    }

    /**
     * Mark every notification as read.
     */
    public function markAllRead(): RedirectResponse
    {
        $this->service->markAllRead((int) auth()->id());

        return back();
    }

    /**
     * Dismiss (hide) a notification from the user's feed.
     */
    public function destroy(Notification $notification): RedirectResponse
    {
        $this->service->dismiss($notification, (int) auth()->id());

        return back();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function priorityOptions(): array
    {
        return array_map(
            static fn (NotificationPriority $priority): array => [
                'value' => $priority->value,
                'label' => $priority->label(),
            ],
            NotificationPriority::cases(),
        );
    }
}
