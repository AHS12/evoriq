<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Always send the user to the dashboard after login, ignoring any
     * previously intended URL (e.g. the page that redirected to login).
     */
    public function toResponse($request): RedirectResponse
    {
        return to_route('dashboard');
    }
}
