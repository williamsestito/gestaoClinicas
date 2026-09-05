<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\SetActiveOrganizationAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SetActiveOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrganizationContextController extends Controller
{
    public function edit(): Response|RedirectResponse|SymfonyResponse
    {
        $user = Auth::guard('web')->user();

        // Superadmin enxerga todas as organizações ativas (acesso global);
        // os demais usuários só veem as organizações onde têm vínculo.
        $organizations = $user->is_platform_admin
            ? Organization::query()->where('status', OrganizationStatus::Active)->orderBy('name')->get()
            : $user->organizationMemberships()
                ->with('organization')
                ->where('status', OrganizationMembershipStatus::Active)
                ->get()
                ->pluck('organization')
                ->filter(fn (?Organization $organization) => $organization?->status === OrganizationStatus::Active)
                ->values();

        // Guarda defensiva: instalação single-tenant (ADR-010) sem nenhuma
        // organização ainda cadastrada, ou onde todas foram suspensas depois
        // que o platform admin chegou aqui — nunca renderiza o seletor
        // vazio, manda para o painel administrativo (Filament) que conduz à
        // criação (ver EnsureActiveOrganization, mesma regra e mesmo motivo
        // para usar Inertia::location() em vez de redirect() comum: esta
        // rota é sempre visitada via Inertia, e /admin devolve HTML puro do
        // Filament).
        if ($organizations->isEmpty() && $user->is_platform_admin) {
            return Inertia::location('/admin');
        }

        return Inertia::render('context/OrganizationSelector', [
            'organizations' => $organizations,
        ]);
    }

    public function update(SetActiveOrganizationRequest $request, SetActiveOrganizationAction $action): RedirectResponse
    {
        $organization = Organization::query()->findOrFail((string) $request->validated('organization_id'));

        $action->handle($request, $request->user('web'), $organization);

        return to_route('dashboard');
    }
}
