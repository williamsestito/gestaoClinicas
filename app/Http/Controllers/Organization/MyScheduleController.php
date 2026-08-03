<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Professional;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ponto de entrada de "Minha agenda" — nunca aceita um `professional_id` do
 * frontend: o profissional é sempre resolvido a partir do usuário
 * autenticado e da organização ativa. Quando o vínculo existe, a Action
 * simplesmente encaminha para as telas administrativas de jornada/ausências
 * já existentes (App\Http\Controllers\Organization\ProfessionalWorkingHourController
 * e ProfessionalTimeBlockController) — a Policy já autoriza o próprio
 * profissional a gerenciá-las (ver App\Policies\ProfessionalPolicy::hasOwnAccess()),
 * então reaproveitamos a mesma página em vez de duplicar formulários e
 * componentes.
 */
class MyScheduleController extends Controller
{
    public function availability(TenantContext $tenant): Response|RedirectResponse
    {
        $professional = $this->resolveOwnProfessional($tenant);

        if ($professional === null) {
            return $this->emptyState();
        }

        return to_route('settings.professionals.availability.index', $professional);
    }

    public function timeBlocks(TenantContext $tenant): Response|RedirectResponse
    {
        $professional = $this->resolveOwnProfessional($tenant);

        if ($professional === null) {
            return $this->emptyState();
        }

        return to_route('settings.professionals.time-blocks.index', $professional);
    }

    private function resolveOwnProfessional(TenantContext $tenant): ?Professional
    {
        $organization = $tenant->organization();

        if ($organization === null) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->professionals()
            ->where('organization_id', $organization->id)
            ->where('status', RecordStatus::Active)
            ->first();
    }

    private function emptyState(): Response
    {
        return Inertia::render('settings/my-schedule/Show', []);
    }
}
