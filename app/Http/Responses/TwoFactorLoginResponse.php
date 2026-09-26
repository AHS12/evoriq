<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * Always send the user to the dashboard after two-factor authentication.
     */
    public function toResponse($request): RedirectResponse
    {
        return to_route('dashboard');
    }
}
