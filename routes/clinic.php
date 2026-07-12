<?php

use App\Http\Controllers\Organization\OnboardingController;
use App\Http\Controllers\Organization\OrganizationContextController;
use App\Http\Controllers\Organization\OrganizationSettingsController;
use App\Http\Controllers\Organization\UnitContextController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\PostalCodeLookupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    // Onboarding: acessível mesmo sem organização ativa (é como ela é criada).
    Route::get('onboarding/organization', [OnboardingController::class, 'create'])
        ->name('onboarding.organization.create');
    Route::post('onboarding/organization', [OnboardingController::class, 'store'])
        ->name('onboarding.organization.store');

    // Seletor de organização: acessível sem organização ativa (é onde se escolhe uma).
    Route::get('context/organization', [OrganizationContextController::class, 'edit'])
        ->name('context.organization.edit');
    Route::put('context/organization', [OrganizationContextController::class, 'update'])
        ->name('context.organization.update');

    Route::middleware('tenant.active-organization')->group(function () {
        Route::get('dashboard', fn () => Inertia::render('Dashboard', [
            'appEnvironment' => app()->environment(),
        ]))->name('dashboard');

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

        Route::middleware('tenant.unit-membership')->group(function () {
            Route::get('settings/units/{unit}/edit', [UnitController::class, 'edit'])
                ->name('settings.units.edit');
            Route::put('settings/units/{unit}', [UnitController::class, 'update'])
                ->name('settings.units.update');
        });

        // Endpoint interno (JSON) usado pelo formulário de endereço para
        // preencher rua/bairro/cidade/UF a partir do CEP. Não é uma API pública.
        Route::get('cep/{postalCode}', PostalCodeLookupController::class)
            ->where('postalCode', '[0-9\-]{8,9}')
            ->middleware('throttle:30,1')
            ->name('postal-code.lookup');
    });
});
