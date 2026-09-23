<?php

namespace App\Http\Controllers\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SetPasswordRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    /**
     * Show the set-password form for an invited user.
     */
    public function show(Request $request, User $user): Response|RedirectResponse
    {
        if (! $this->invitationIsValid($request, $user)) {
            return $this->expired();
        }

        return Inertia::render('auth/accept-invitation', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'action' => $request->fullUrl(),
        ]);
    }

    /**
     * Set the password, activate the account and sign the user in.
     */
    public function store(SetPasswordRequest $request, User $user): RedirectResponse
    {
        if (! $this->invitationIsValid($request, $user)) {
            return $this->expired();
        }

        $this->users->acceptInvitation($user, (string) $request->validated('password'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * Determine that the signed link still matches the current invitation.
     */
    private function invitationIsValid(Request $request, User $user): bool
    {
        $token = $request->query('token');

        return $user->status === UserStatus::INVITED
            && $user->invitation_token !== null
            && is_string($token)
            && hash_equals($user->invitation_token, $token);
    }

    private function expired(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('This invitation link is no longer valid.'),
        ]);

        return redirect()->route('login');
    }
}
