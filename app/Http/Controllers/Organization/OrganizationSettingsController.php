<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\UpdateOrganizationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(Request $request, TenantContext $tenant): Response
    {
        return Inertia::render('settings/Organization', [
            'organization' => $tenant->organization(),
            // Antes só considerava o proprietário — ignorava que um
            // Administrador da clínica também recebe `organization.update`
            // por papel (ver SystemRole::ClinicAdmin), deixando o
            // formulário inteiro desabilitado para quem na verdade tinha
            // permissão de editar.
            'canUpdate' => $tenant->organization() !== null
                && $request->user()?->can('update', $tenant->organization()) === true,
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        UpdateOrganizationAction $action,
        TenantContext $tenant,
    ): RedirectResponse {
        $action->handle($tenant->organization(), $request->validated());

        return back();
    }
}
