<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\AssignRolePermissionsAction;
use App\Actions\Organization\CreateRoleAction;
use App\Actions\Organization\DeleteRoleAction;
use App\Actions\Organization\DuplicateRoleAction;
use App\Actions\Organization\UpdateRoleAction;
use App\Enums\PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignRolePermissionsRequest;
use App\Http\Requests\Organization\CreateRoleRequest;
use App\Http\Requests\Organization\UpdateRoleRequest;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $this->authorize('viewAny', [Role::class, $organization]);

        $roles = $organization->roles()
            ->withCount('organizationMemberships')
            ->with('permissions:id,key')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/roles/Index', [
            'roles' => $roles,
            'permissions' => collect(PermissionKey::cases())
                ->map(fn (PermissionKey $key) => [
                    'key' => $key->value,
                    'label' => $key->label(),
                    'group' => $key->group(),
                ])
                ->groupBy('group'),
        ]);
    }

    public function store(CreateRoleRequest $request, TenantContext $tenant, CreateRoleAction $action): RedirectResponse
    {
        $data = $request->validated();

        $action->handle(
            organization: $tenant->organization(),
            name: $data['name'],
            description: $data['description'] ?? null,
            permissionKeys: $data['permissions'] ?? [],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Papel criado com sucesso.']);

        return back();
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
        UpdateRoleAction $action,
        AssignRolePermissionsAction $assignPermissions,
    ): RedirectResponse {
        $data = $request->validated();

        $action->handle($role, $data['name'], $data['description'] ?? null);

        if (array_key_exists('permissions', $data) && $request->user()?->can('assignPermissions', $role)) {
            $assignPermissions->handle($role, $data['permissions']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Papel atualizado com sucesso.']);

        return back();
    }

    public function assignPermissions(AssignRolePermissionsRequest $request, Role $role, AssignRolePermissionsAction $action): RedirectResponse
    {
        $action->handle($role, $request->validated('permissions') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permissões atualizadas com sucesso.']);

        return back();
    }

    public function duplicate(Role $role, DuplicateRoleAction $action): RedirectResponse
    {
        $this->authorize('create', [Role::class, $role->organization]);

        $action->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Papel duplicado com sucesso.']);

        return back();
    }

    public function destroy(Role $role, DeleteRoleAction $action): RedirectResponse
    {
        $this->authorize('delete', $role);

        $action->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Papel excluído com sucesso.']);

        return back();
    }
}
