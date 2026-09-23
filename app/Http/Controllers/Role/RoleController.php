<?php

namespace App\Http\Controllers\Role;

use App\DTOs\Role\RoleDTO;
use App\DTOs\Role\RoleFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\Role\PermissionResource;
use App\Http\Resources\Role\RoleResource;
use App\Models\Role;
use App\Services\Role\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    /**
     * List roles.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('roles/index', [
            'roles' => RoleResource::collection(
                $this->roles->paginate(RoleFilterDTO::fromRequest($request)),
            ),
            'filters' => [
                'search' => $request->input('search'),
                'order_by' => $request->input('order_by', 'name'),
                'order_direction' => $request->input('order_direction', 'asc'),
                'per_page' => $request->input('per_page'),
                'page' => $request->input('page'),
            ],
            'permissions' => PermissionResource::collection(
                $this->roles->permissionCatalog(),
            )->resolve(),
        ]);
    }

    /**
     * Create a role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $this->roles->create(RoleDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role created.'),
        ]);

        return to_route('roles.index');
    }

    /**
     * Update a role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $this->roles->update($role, RoleDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role updated.'),
        ]);

        return to_route('roles.index');
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roles->delete($role);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role deleted.'),
        ]);

        return to_route('roles.index');
    }
}
