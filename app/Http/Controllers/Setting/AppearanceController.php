<?php

namespace App\Http\Controllers\Setting;

use App\DTOs\Setting\AppearanceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateAppearanceRequest;
use App\Services\Setting\AppearanceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function __construct(
        private readonly AppearanceService $appearance,
    ) {}

    /**
     * Show the user's appearance settings.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/appearance');
    }

    /**
     * Persist the user's appearance preferences.
     */
    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $this->appearance->update($request->user(), AppearanceDTO::fromRequest($request));

        if (! $request->boolean('silent')) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Appearance updated.'),
            ]);
        }

        return back();
    }
}
