<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\AssignUserUnitsAction;
use App\Actions\Organization\ChangeUserActiveStatusAction;
use App\Actions\Organization\InviteUserAction;
use App\Actions\Organization\UpdateUserMembershipAction;
use App\Enums\InvitationStatus;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\InviteUserRequest;
use App\Http\Requests\Organization\UpdateUserMembershipRequest;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $this->authorize('viewAny', [OrganizationMembership::class, $organization]);

        $memberships = $organization->memberships()
            ->with(['user:id,name,email,is_active,last_login_at', 'role:id,name,slug', 'unitMemberships.unit:id,name'])
            ->get();

        $invitations = $organization->invitations()
            ->whereIn('status', [InvitationStatus::Pending, InvitationStatus::Expired])
            ->with('role:id,name')
            ->latest()
            ->get();

        return Inertia::render('settings/users/Index', [
            'memberships' => $memberships,
            'invitations' => $invitations,
            'roles' => $organization->roles()->orderBy('name')->get(['id', 'name', 'is_system']),
            'units' => $organization->units()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function invite(InviteUserRequest $request, TenantContext $tenant, InviteUserAction $action): RedirectResponse
    {
        $data = $request->validated();
        $organization = $tenant->organization();

        $role = $data['role_id'] ?? null ? Role::query()->where('id', $data['role_id'])->first() : null;
        $units = Unit::query()->whereIn('id', $data['unit_ids'] ?? [])->get();

        $action->handle(
            organization: $organization,
            invitedBy: $request->user(),
            email: $data['email'],
            role: $role,
            units: $units,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Convite enviado com sucesso.']);

        return back();
    }

    public function updateMembership(
        UpdateUserMembershipRequest $request,
        OrganizationMembership $membership,
        UpdateUserMembershipAction $updateMembership,
        AssignUserUnitsAction $assignUnits,
    ): RedirectResponse {
        $data = $request->validated();
        $user = $request->user();

        if ((array_key_exists('role_id', $data) || array_key_exists('admin_note', $data)) && $user?->can('assignRoles', $membership)) {
            $role = ($data['role_id'] ?? null) ? Role::query()->where('id', $data['role_id'])->first() : null;
            $updateMembership->handle($membership, $role, $data['admin_note'] ?? $membership->admin_note);
        }

        if (array_key_exists('unit_ids', $data) && $user?->can('assignUnits', $membership)) {
            $assignUnits->handle($membership, $data['unit_ids'], $data['primary_unit_id'] ?? null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário atualizado com sucesso.']);

        return back();
    }

    public function activate(OrganizationMembership $membership, ChangeUserActiveStatusAction $action): RedirectResponse
    {
        $this->authorize('activate', $membership);

        $action->handle($membership, true);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário ativado com sucesso.']);

        return back();
    }

    public function deactivate(OrganizationMembership $membership, ChangeUserActiveStatusAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $membership);

        $action->handle($membership, false);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário inativado com sucesso.']);

        return back();
    }
}
