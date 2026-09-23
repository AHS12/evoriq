<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Validator;

trait ValidatesRoleAssignment
{
    /**
     * Only super admins may grant the Super Admin role.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user instanceof User || $user->isSuperAdmin()) {
                    return;
                }

                /** @var array<int, string> $roles */
                $roles = (array) $this->input('roles', []);

                if (in_array(UserRole::SUPER_ADMIN->value, $roles, true)) {
                    $validator->errors()->add(
                        'roles',
                        __('Only a super admin can assign the Super Admin role.'),
                    );
                }
            },
        ];
    }
}
