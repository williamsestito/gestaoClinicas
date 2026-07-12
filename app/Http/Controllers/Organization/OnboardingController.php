<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\OnboardOrganizationAction;
use App\Data\Organization\OnboardOrganizationData;
use App\Enums\LegalEntityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\OnboardOrganizationRequest;
use App\Support\Documents\BrazilianState;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Onboarding/Organization', [
            'legalEntityTypes' => array_map(
                fn (LegalEntityType $type) => ['value' => $type->value, 'label' => $type->label()],
                LegalEntityType::cases(),
            ),
            'states' => BrazilianState::codes(),
        ]);
    }

    public function store(OnboardOrganizationRequest $request, OnboardOrganizationAction $action): RedirectResponse
    {
        $data = OnboardOrganizationData::fromArray($request->validated());

        $action->handle($request->user(), $data, $request);

        return to_route('dashboard');
    }
}
