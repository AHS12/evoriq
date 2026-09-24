<?php

namespace App\Http\Controllers\Setting;

use App\DTOs\Setting\NotificationPreferenceDTO;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateNotificationPreferenceRequest;
use App\Services\Setting\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected NotificationPreferenceService $preferences,
    ) {}

    /**
     * Show the per-user notification preference settings.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notifications', [
            'preferences' => $this->preferences->forUser($request->user()),
            'types' => array_map(
                static fn (NotificationType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                NotificationType::cases(),
            ),
        ]);
    }

    /**
     * Persist the per-user notification preferences.
     */
    public function update(UpdateNotificationPreferenceRequest $request): RedirectResponse
    {
        $this->preferences->update($request->user(), NotificationPreferenceDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Notification preferences saved.'),
        ]);

        return back();
    }
}
