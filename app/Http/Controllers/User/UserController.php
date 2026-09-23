<?php

namespace App\Http\Controllers\User;

use App\DTOs\User\UserDTO;
use App\DTOs\User\UserFilterDTO;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignRolesRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    /**
     * List users.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('users/index', [
            'users' => UserResource::collection(
                $this->users->paginate(UserFilterDTO::fromRequest($request)),
            ),
            'filters' => [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
                'status' => $request->input('status'),
                'order_by' => $request->input('order_by', 'created_at'),
                'order_direction' => $request->input('order_direction', 'desc'),
                'per_page' => $request->input('per_page'),
                'page' => $request->input('page'),
            ],
            'roles' => $this->roleNames(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Create a user and send the invitation.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $this->users->create(UserDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User created and invitation sent.'),
        ]);

        return to_route('users.index');
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->update($user, UserDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User updated.'),
        ]);

        return to_route('users.index');
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User deleted.'),
        ]);

        return to_route('users.index');
    }

    /**
     * Replace a user's roles.
     */
    public function assignRoles(AssignRolesRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        /** @var array<int, string> $roles */
        $roles = (array) $request->validated('roles');

        $this->users->assignRoles($user, $roles);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Roles updated.'),
        ]);

        return to_route('users.index');
    }

    /**
     * Re-send the invitation email.
     */
    public function resendInvitation(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->resendInvitation($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation sent.'),
        ]);

        return to_route('users.index');
    }

    /**
     * Suspend or reactivate a user.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = $user->isSuspended() ? UserStatus::ACTIVE : UserStatus::SUSPENDED;

        $this->users->updateStatus($user, $status);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === UserStatus::SUSPENDED
                ? __('User suspended.')
                : __('User reactivated.'),
        ]);

        return to_route('users.index');
    }

    /**
     * Email the user a password reset link.
     */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->sendPasswordReset($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Password reset link sent.'),
        ]);

        return to_route('users.index');
    }

    /**
     * @return array<int, string>
     */
    private function roleNames(): array
    {
        return Role::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (UserStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            UserStatus::cases(),
        );
    }
}
