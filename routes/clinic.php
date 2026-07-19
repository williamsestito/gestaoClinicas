<?php

use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\LegalEntityController;
use App\Http\Controllers\Organization\OnboardingController;
use App\Http\Controllers\Organization\OrganizationContextController;
use App\Http\Controllers\Organization\OrganizationSettingsController;
use App\Http\Controllers\Organization\UnitContextController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\PostalCodeLookupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinic Routes
|--------------------------------------------------------------------------
|
| Rotas do contexto autenticado da clinica (equipe/profissionais): dashboard,
| onboarding de organização, seleção de organização/unidade ativa e
| configurações de organização/unidades (Fase 1). Módulos de negócio
| (agenda, pacientes, prontuário, etc.) serão adicionados em fases futuras.
|
*/

Route::middleware(['auth', 'verified', 'tenant.organization', 'tenant.unit'])->group(function () {

    // Onboarding: acessível sem organização ativa (é como ela é criada).
    // Bloqueado para quem já tem uma organização (evita reacesso e criação
    // ilimitada de clínicas pela mesma rota).
    Route::middleware('tenant.no-active-organization')->group(function () {
        Route::get('onboarding/organization', [OnboardingController::class, 'create'])
            ->name('onboarding.organization.create');
        Route::post('onboarding/organization', [OnboardingController::class, 'store'])
            ->name('onboarding.organization.store');
    });

    // Seletor de organização: acessível sem organização ativa (é onde se escolhe uma).
    Route::get('context/organization', [OrganizationContextController::class, 'edit'])
        ->name('context.organization.edit');
    Route::put('context/organization', [OrganizationContextController::class, 'update'])
        ->name('context.organization.update');

    Route::middleware('tenant.active-organization')->group(function () {
        // Rotas operacionais: exigem também uma unidade ativa resolvida.
        Route::middleware('tenant.active-unit')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])
                ->name('dashboard');
        });

        Route::get('context/unit', [UnitContextController::class, 'edit'])
            ->name('context.unit.edit');
        Route::put('context/unit', [UnitContextController::class, 'update'])
            ->name('context.unit.update');

        Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])
            ->name('settings.organization.edit');
        Route::put('settings/organization', [OrganizationSettingsController::class, 'update'])
            ->name('settings.organization.update');

        Route::get('settings/units', [UnitController::class, 'index'])
            ->name('settings.units.index');
        Route::get('settings/units/create', [UnitController::class, 'create'])
            ->name('settings.units.create');
        Route::post('settings/units', [UnitController::class, 'store'])
            ->name('settings.units.store');
        // Restauração usa {unit} sem binding de Eloquent (o registro está
        // excluído logicamente) — a checagem de organização é manual no controller.
        Route::post('settings/units/{unit}/restore', [UnitController::class, 'restore'])
            ->name('settings.units.restore');
        // Fora do grupo tenant.unit-membership pelo mesmo motivo do restore:
        // essa rota também precisa funcionar quando a unidade está inativa
        // (é como ela volta a ficar ativa) — a checagem de organização é
        // manual no controller, e a autorização (owner) continua via Policy.
        Route::patch('settings/units/{unit}/status', [UnitController::class, 'updateStatus'])
            ->name('settings.units.status');

        Route::middleware('tenant.unit-membership')->group(function () {
            Route::get('settings/units/{unit}/edit', [UnitController::class, 'edit'])
                ->name('settings.units.edit');
            Route::put('settings/units/{unit}', [UnitController::class, 'update'])
                ->name('settings.units.update');
            Route::put('settings/units/{unit}/headquarters', [UnitController::class, 'makeHeadquarters'])
                ->name('settings.units.headquarters');
            Route::delete('settings/units/{unit}', [UnitController::class, 'destroy'])
                ->name('settings.units.destroy');
        });

        Route::get('settings/legal-entities', [LegalEntityController::class, 'index'])
            ->name('settings.legal-entities.index');
        Route::get('settings/legal-entities/create', [LegalEntityController::class, 'create'])
            ->name('settings.legal-entities.create');
        Route::post('settings/legal-entities', [LegalEntityController::class, 'store'])
            ->name('settings.legal-entities.store');
        Route::post('settings/legal-entities/{legalEntity}/restore', [LegalEntityController::class, 'restore'])
            ->name('settings.legal-entities.restore');

        Route::middleware('tenant.legal-entity-membership')->group(function () {
            Route::get('settings/legal-entities/{legalEntity}/edit', [LegalEntityController::class, 'edit'])
                ->name('settings.legal-entities.edit');
            Route::put('settings/legal-entities/{legalEntity}', [LegalEntityController::class, 'update'])
                ->name('settings.legal-entities.update');
            Route::patch('settings/legal-entities/{legalEntity}/status', [LegalEntityController::class, 'updateStatus'])
                ->name('settings.legal-entities.status');
            Route::put('settings/legal-entities/{legalEntity}/primary', [LegalEntityController::class, 'makePrimary'])
                ->name('settings.legal-entities.primary');
            Route::delete('settings/legal-entities/{legalEntity}', [LegalEntityController::class, 'destroy'])
                ->name('settings.legal-entities.destroy');
        });

        // Endpoint interno (JSON) usado pelo formulário de endereço para
        // preencher rua/bairro/cidade/UF a partir do CEP. Não é uma API pública.
        Route::get('cep/{postalCode}', PostalCodeLookupController::class)
            ->where('postalCode', '[0-9\-]{8,9}')
            ->middleware('throttle:30,1')
            ->name('postal-code.lookup');
    });
});
