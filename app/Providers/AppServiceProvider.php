<?php

namespace App\Providers;

use App\Models\AppointmentRequest;
use App\Models\Invitation;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteBenefit;
use App\Models\SiteFaq;
use App\Models\SiteGalleryItem;
use App\Models\SiteProfessional;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use App\Services\ApiCepPostalCodeLookup;
use App\Services\AwesomeApiPostalCodeLookup;
use App\Services\PostalCodeLookup;
use App\Services\PostalCodeLookupChain;
use App\Services\PostalCodeProvider;
use App\Services\ViaCepPostalCodeLookup;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<PostalCodeProvider>> */
    private const CEP_PROVIDER_MAP = [
        'awesomeapi' => AwesomeApiPostalCodeLookup::class,
        'apicep' => ApiCepPostalCodeLookup::class,
        'viacep' => ViaCepPostalCodeLookup::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(PostalCodeLookup::class, function (Container $app): PostalCodeLookupChain {
            /** @var array<int, string> $providerKeys */
            $providerKeys = config('cep.providers', array_keys(self::CEP_PROVIDER_MAP));

            $providers = array_map(
                fn (string $key) => $app->make(self::CEP_PROVIDER_MAP[$key]),
                $providerKeys,
            );

            return new PostalCodeLookupChain($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAuthEvents();
    }

    /**
     * Registra o horário do último acesso do usuário — exibido na página
     * de gestão de usuários ("visualizar último acesso, caso exista").
     */
    protected function configureAuthEvents(): void
    {
        Event::listen(function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });
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
            'user' => User::class,
            'role' => Role::class,
            'permission' => Permission::class,
            'invitation' => Invitation::class,
            'site_setting' => SiteSetting::class,
            'site_benefit' => SiteBenefit::class,
            'site_service' => SiteService::class,
            'site_professional' => SiteProfessional::class,
            'site_gallery_item' => SiteGalleryItem::class,
            'site_testimonial' => SiteTestimonial::class,
            'site_faq' => SiteFaq::class,
            'appointment_request' => AppointmentRequest::class,
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
