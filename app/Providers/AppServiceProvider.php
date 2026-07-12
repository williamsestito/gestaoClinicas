<?php

namespace App\Providers;

use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Services\PostalCodeLookup;
use App\Services\ViaCepPostalCodeLookup;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(PostalCodeLookup::class, ViaCepPostalCodeLookup::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
    }

    /**
     * Morph map explícito para os relacionamentos polimórficos (Address,
     * AuditLog) — nunca armazenamos o nome completo da classe no banco.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'organization' => Organization::class,
            'legal_entity' => LegalEntity::class,
            'unit' => Unit::class,
            'organization_membership' => OrganizationMembership::class,
            'unit_membership' => UnitMembership::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
