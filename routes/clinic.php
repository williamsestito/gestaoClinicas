<?php

use App\Http\Controllers\Organization\AppointmentRequestController;
use App\Http\Controllers\Organization\AuditLogController;
use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\LegalEntityController;
use App\Http\Controllers\Organization\OnboardingController;
use App\Http\Controllers\Organization\OrganizationContextController;
use App\Http\Controllers\Organization\OrganizationSettingsController;
use App\Http\Controllers\Organization\RoleController;
use App\Http\Controllers\Organization\SeoMarketingController;
use App\Http\Controllers\Organization\SiteBenefitController;
use App\Http\Controllers\Organization\SiteContentController;
use App\Http\Controllers\Organization\SiteFaqController;
use App\Http\Controllers\Organization\SiteGalleryItemController;
use App\Http\Controllers\Organization\SiteProfessionalController;
use App\Http\Controllers\Organization\SiteSectionsController;
use App\Http\Controllers\Organization\SiteServiceController;
use App\Http\Controllers\Organization\SiteTestimonialController;
use App\Http\Controllers\Organization\UnitContextController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\Organization\UserManagementController;
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
        ->middleware('throttle:20,1')
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
            ->middleware('throttle:20,1')
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

        Route::get('settings/roles', [RoleController::class, 'index'])
            ->name('settings.roles.index');
        Route::post('settings/roles', [RoleController::class, 'store'])
            ->name('settings.roles.store');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])
            ->name('settings.roles.update');
        Route::put('settings/roles/{role}/permissions', [RoleController::class, 'assignPermissions'])
            ->name('settings.roles.permissions');
        Route::post('settings/roles/{role}/duplicate', [RoleController::class, 'duplicate'])
            ->name('settings.roles.duplicate');
        Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])
            ->name('settings.roles.destroy');

        Route::get('settings/users', [UserManagementController::class, 'index'])
            ->name('settings.users.index');
        Route::post('settings/users/invite', [UserManagementController::class, 'invite'])
            ->name('settings.users.invite');
        Route::put('settings/users/{membership}', [UserManagementController::class, 'updateMembership'])
            ->name('settings.users.update');
        Route::patch('settings/users/{membership}/activate', [UserManagementController::class, 'activate'])
            ->name('settings.users.activate');
        Route::patch('settings/users/{membership}/deactivate', [UserManagementController::class, 'deactivate'])
            ->name('settings.users.deactivate');

        Route::post('settings/invitations/{invitation}/cancel', [InvitationController::class, 'cancel'])
            ->name('settings.invitations.cancel');
        Route::post('settings/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('settings.invitations.resend');

        Route::get('settings/site', [SiteContentController::class, 'edit'])
            ->name('settings.site.edit');
        Route::put('settings/site', [SiteContentController::class, 'update'])
            ->name('settings.site.update');
        Route::delete('settings/site/hero-image', [SiteContentController::class, 'destroyHeroImage'])
            ->name('settings.site.hero-image.destroy');
        Route::delete('settings/site/logo', [SiteContentController::class, 'destroyLogo'])
            ->name('settings.site.logo.destroy');
        Route::delete('settings/site/favicon', [SiteContentController::class, 'destroyFavicon'])
            ->name('settings.site.favicon.destroy');
        Route::patch('settings/site/publish', [SiteContentController::class, 'publish'])
            ->name('settings.site.publish');
        Route::patch('settings/site/unpublish', [SiteContentController::class, 'unpublish'])
            ->name('settings.site.unpublish');

        Route::get('settings/seo', [SeoMarketingController::class, 'edit'])
            ->name('settings.seo.edit');
        Route::put('settings/seo', [SeoMarketingController::class, 'update'])
            ->name('settings.seo.update');

        Route::get('settings/site/sections', [SiteSectionsController::class, 'edit'])
            ->name('settings.site.sections.edit');
        Route::put('settings/site/sections', [SiteSectionsController::class, 'update'])
            ->name('settings.site.sections.update');

        // Coleções de conteúdo da landing pública (benefícios, serviços,
        // profissionais, galeria, depoimentos, FAQ) — mesmo padrão de
        // rotas em todas, ver App\Http\Controllers\Organization\Site*Controller.
        // $param precisa bater com o nome do argumento tipado no Controller
        // (route-model-binding implícito do Laravel resolve pelo nome).
        foreach ([
            'benefits' => [SiteBenefitController::class, 'siteBenefit'],
            'services' => [SiteServiceController::class, 'siteService'],
            'professionals' => [SiteProfessionalController::class, 'siteProfessional'],
            'gallery' => [SiteGalleryItemController::class, 'siteGalleryItem'],
            'testimonials' => [SiteTestimonialController::class, 'siteTestimonial'],
            'faq' => [SiteFaqController::class, 'siteFaq'],
        ] as $segment => [$controller, $param]) {
            Route::get("settings/site/{$segment}", [$controller, 'index'])
                ->name("settings.site.{$segment}.index");
            Route::post("settings/site/{$segment}", [$controller, 'store'])
                ->name("settings.site.{$segment}.store");
            Route::patch("settings/site/{$segment}/reorder", [$controller, 'reorder'])
                ->name("settings.site.{$segment}.reorder");
            Route::put("settings/site/{$segment}/{{$param}}", [$controller, 'update'])
                ->name("settings.site.{$segment}.update");
            Route::patch("settings/site/{$segment}/{{$param}}/toggle", [$controller, 'toggle'])
                ->name("settings.site.{$segment}.toggle");
            Route::delete("settings/site/{$segment}/{{$param}}", [$controller, 'destroy'])
                ->name("settings.site.{$segment}.destroy");
        }

        Route::get('settings/site/appointment-requests', [AppointmentRequestController::class, 'index'])
            ->name('settings.site.appointment-requests.index');
        Route::patch('settings/site/appointment-requests/{appointmentRequest}/status', [AppointmentRequestController::class, 'updateStatus'])
            ->name('settings.site.appointment-requests.status');
        Route::patch('settings/site/appointment-requests/{appointmentRequest}/notes', [AppointmentRequestController::class, 'updateNotes'])
            ->name('settings.site.appointment-requests.notes');

        Route::get('settings/audit', [AuditLogController::class, 'index'])
            ->name('settings.audit.index');

        // Endpoint interno (JSON) usado pelo formulário de endereço para
        // preencher rua/bairro/cidade/UF a partir do CEP. Não é uma API pública.
        Route::get('cep/{postalCode}', PostalCodeLookupController::class)
            ->where('postalCode', '[0-9\-]{8,9}')
            ->middleware('throttle:30,1')
            ->name('postal-code.lookup');
    });
});
