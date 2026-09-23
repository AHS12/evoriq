<?php

namespace App\Services\User;

use App\DTOs\User\UserDTO;
use App\DTOs\User\UserFilterDTO;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Number of days a user invitation link stays valid.
     */
    public const INVITATION_EXPIRY_DAYS = 7;

    public function __construct(
        protected UserRepositoryInterface $users,
    ) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(UserFilterDTO $filters): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $filters->perPage, ['roles']);
    }

    public function find(int $id): ?User
    {
        return $this->users->findById($id, ['roles']);
    }

    /**
     * Create an invited user, assign roles and email an invitation link.
     */
    public function create(UserDTO $dto): User
    {
        $user = DB::transaction(function () use ($dto): User {
            $user = $this->users->create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Str::password(32),
                'status' => UserStatus::INVITED,
                'invitation_token' => (string) Str::uuid(),
                'invitation_sent_at' => now(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($dto->roles !== []) {
                $this->users->assignRoles($user, $dto->roles);
            }

            return $user->load('roles');
        });

        $this->sendInvitation($user);

        return $user;
    }

    /**
     * Update a user's profile and (for non super admins) roles.
     */
    public function update(User $user, UserDTO $dto): User
    {
        return DB::transaction(function () use ($user, $dto): User {
            $data = [
                'name' => $dto->name,
                'email' => $dto->email,
                'updated_by' => auth()->id(),
            ];

            if ($dto->status !== null) {
                $data['status'] = $dto->status;
            }

            $user = $this->users->update($user, $data);

            if (! $user->isSuperAdmin() && $dto->roles !== []) {
                $user = $this->users->assignRoles($user, $dto->roles);
            }

            return $user->load('roles');
        });
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function assignRoles(User $user, array $roles): User
    {
        return DB::transaction(fn (): User => $this->users->assignRoles($user, $roles)->load('roles'));
    }

    /**
     * Move a user to the given status, re-issuing an invitation when needed.
     */
    public function updateStatus(User $user, UserStatus $status): User
    {
        if ($user->isSuperAdmin() && $status === UserStatus::SUSPENDED) {
            throw new AuthorizationException(__('The super admin cannot be suspended.'));
        }

        $user = DB::transaction(function () use ($user, $status): User {
            $data = [
                'status' => $status,
                'updated_by' => auth()->id(),
            ];

            if ($status === UserStatus::INVITED) {
                $data['invitation_token'] = (string) Str::uuid();
                $data['invitation_sent_at'] = now();
            }

            return $this->users->update($user, $data)->load('roles');
        });

        if ($status === UserStatus::INVITED) {
            $this->sendInvitation($user);
        }

        return $user;
    }

    /**
     * Re-issue an invitation, invalidating any previously sent link.
     */
    public function resendInvitation(User $user): User
    {
        $user = DB::transaction(fn (): User => $this->users->update($user, [
            'status' => UserStatus::INVITED,
            'invitation_token' => (string) Str::uuid(),
            'invitation_sent_at' => now(),
            'updated_by' => auth()->id(),
        ]));

        $this->sendInvitation($user);

        return $user;
    }

    /**
     * Email the standard password reset link to the user.
     */
    public function sendPasswordReset(User $user): void
    {
        Password::broker()->sendResetLink(['email' => $user->email]);
    }

    /**
     * Complete an invitation: set the password and activate the account.
     */
    public function acceptInvitation(User $user, string $password): User
    {
        return DB::transaction(fn (): User => $this->users->update($user, [
            'password' => $password,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
            'invitation_token' => null,
            'invitation_sent_at' => null,
        ]));
    }

    public function delete(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            throw new AuthorizationException(__('The super admin cannot be deleted.'));
        }

        return DB::transaction(fn (): bool => $this->users->delete($user));
    }

    /**
     * Build the signed accept-invitation URL for a user.
     */
    public function invitationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'invitations.accept',
            now()->addDays(self::INVITATION_EXPIRY_DAYS),
            ['user' => $user->id, 'token' => $user->invitation_token],
        );
    }

    protected function sendInvitation(User $user): void
    {
        $user->notify(new UserInvitation(
            $this->invitationUrl($user),
            self::INVITATION_EXPIRY_DAYS,
        ));
    }
}
