<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\SetActiveOrganizationAction;
use App\Enums\OrganizationMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SetActiveOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationContextController extends Controller
{
    public function edit(): Response
    {
        $organizations = Auth::user()
            ->organizationMemberships()
            ->with('organization')
            ->where('status', OrganizationMembershipStatus::Active)
            ->get()
            ->pluck('organization');

        return Inertia::render('context/OrganizationSelector', [
            'organizations' => $organizations,
        ]);
    }

    public function update(SetActiveOrganizationRequest $request, SetActiveOrganizationAction $action): RedirectResponse
    {
        $organization = Organization::query()->findOrFail((string) $request->validated('organization_id'));

        $action->handle($request, $request->user(), $organization);

        return to_route('dashboard');
    }
}
