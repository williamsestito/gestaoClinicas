<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\DisableOrganizationModuleAction;
use App\Actions\Organization\EnableOrganizationModuleAction;
use App\Enums\ModuleKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationModulesRequest;
use App\Models\OrganizationModule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationModuleController extends Controller
{
    public function edit(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $this->authorize('view', [OrganizationModule::class, $organization]);

        $modules = collect(ModuleKey::toggleable())->map(fn (ModuleKey $key) => [
            'key' => $key->value,
            'label' => $key->label(),
            'enabled' => $organization->hasModule($key),
        ])->values();

        return Inertia::render('settings/modules/Index', [
            'modules' => $modules,
            'canManage' => $request->user()?->can('manage', [OrganizationModule::class, $organization]) === true,
        ]);
    }

    public function update(
        UpdateOrganizationModulesRequest $request,
        EnableOrganizationModuleAction $enable,
        DisableOrganizationModuleAction $disable,
        TenantContext $tenant,
    ): RedirectResponse {
        $organization = $tenant->organization();
        $submitted = $request->validated('modules');

        // Itera sobre o catálogo fechado de módulos, nunca sobre as chaves
        // enviadas pelo request — evita confiar em qualquer chave
        // arbitrária que o frontend venha a mandar.
        foreach (ModuleKey::toggleable() as $key) {
            $enabled = (bool) ($submitted[$key->value] ?? false);

            $enabled
                ? $enable->handle($organization, $key)
                : $disable->handle($organization, $key);
        }

        return back();
    }
}
