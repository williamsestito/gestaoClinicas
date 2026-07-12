<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\UpdateOrganizationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        return Inertia::render('settings/Organization', [
            'organization' => $tenant->organization(),
            'isOwner' => (bool) $tenant->membership()?->is_owner,
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
