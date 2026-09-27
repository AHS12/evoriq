<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Services\Setting\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settings,
    ) {}

    /**
     * Show a settings group.
     */
    public function edit(Request $request): Response
    {
        $group = (string) $request->route('group');

        $groups = collect($this->settings->groups())->keyBy('key');

        abort_unless($groups->has($group), 404);

        return Inertia::render("admin/settings/{$group}", [
            'group' => $groups->get($group),
            'groups' => $this->settings->groups(),
        ]);
    }

    /**
     * Update a settings group.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $group = (string) $request->route('group');

        $this->settings->update($request->validated());

        // Groups applied at boot time by SettingsServiceProvider are stale in
        // long-running queue workers until they restart.
        if (in_array($group, ['general', 'mail'], true)) {
            Artisan::call('queue:restart');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Settings saved.'),
        ]);

        return to_route("admin.settings.{$group}.edit");
    }
}
