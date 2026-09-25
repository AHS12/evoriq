<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateLocaleRequest;
use App\Services\Setting\LocaleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LocaleController extends Controller
{
    public function __construct(
        private readonly LocaleService $locale,
    ) {}

    /**
     * Switch the active locale. Persisted per user when authenticated, in the
     * session otherwise (welcome page, pre-signup).
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = (string) $request->validated('locale');

        $user = $request->user();

        if ($user !== null) {
            $this->locale->update($user, $locale);
        }

        $request->session()->put('locale', $locale);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Language updated.'),
        ]);

        return back();
    }
}
