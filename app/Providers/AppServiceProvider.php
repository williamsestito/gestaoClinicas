<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Invitation;
use App\Models\LegalEntity;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAddendum;
use App\Models\MedicalRecordFile;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationModule;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use App\Models\PatientResponsible;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\ProfessionalService;
use App\Models\ProfessionalServiceUnit;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\ServiceUnit;
use App\Models\SessionPackage;
use App\Models\SharedResource;
use App\Models\SiteBenefit;
use App\Models\SiteFaq;
use App\Models\SiteGalleryItem;
use App\Models\SitePartner;
use App\Models\SiteProfessional;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Models\Specialty;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use App\Models\WaitlistEntry;
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
            'organization_module' => OrganizationModule::class,
            'appointment' => Appointment::class,
            'patient' => Patient::class,
            'patient_responsible' => PatientResponsible::class,
            'patient_emergency_contact' => PatientEmergencyContact::class,
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
            'site_partner' => SitePartner::class,
            'site_testimonial' => SiteTestimonial::class,
            'site_faq' => SiteFaq::class,
            'appointment_request' => AppointmentRequest::class,
            'specialty' => Specialty::class,
            'service' => Service::class,
            'professional' => Professional::class,
            'professional_registration' => ProfessionalRegistration::class,
            'professional_specialty' => ProfessionalSpecialty::class,
            'professional_unit' => ProfessionalUnit::class,
            'professional_service' => ProfessionalService::class,
            'service_specialty' => ServiceSpecialty::class,
            'service_unit' => ServiceUnit::class,
            'professional_service_unit' => ProfessionalServiceUnit::class,
            'professional_working_hour' => ProfessionalWorkingHour::class,
            'professional_time_block' => ProfessionalTimeBlock::class,
            'patient_user' => PatientUser::class,
            'patient_user_link' => PatientUserLink::class,
            'resource' => SharedResource::class,
            'session_package' => SessionPackage::class,
            'waitlist_entry' => WaitlistEntry::class,
            'medical_record' => MedicalRecord::class,
            'medical_record_addendum' => MedicalRecordAddendum::class,
            'medical_record_file' => MedicalRecordFile::class,
            'product' => Product::class,
            'sale' => Sale::class,
            'sale_item' => SaleItem::class,
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
