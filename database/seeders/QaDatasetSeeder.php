<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SaleItemType;
use App\Enums\SystemRole;
use App\Enums\WaitlistEntryStatus;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use App\Models\PatientResponsible;
use App\Models\Product;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\Specialty;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Massa de dados de QA/teste manual: 2 organizações completas e totalmente
 * independentes (nada de paciente, agendamento, venda ou profissional
 * compartilhado entre elas), cada uma com no mínimo 5 registros de cada
 * tipo de entidade relevante do domínio — ver SEEDERS.md para a lista
 * completa de credenciais geradas e contagens.
 *
 * Idempotente por coleção, no mesmo padrão de DemoOperationalDataSeeder:
 * cada etapa verifica se a organização já tem registros daquele tipo antes
 * de criar, então rodar `php artisan db:seed --class=QaDatasetSeeder`
 * várias vezes nunca duplica dados. Bloqueado em produção.
 */
class QaDatasetSeeder extends Seeder
{
    /** Senha compartilhada por todo usuário de teste criado por este seeder — nunca usada em produção (ver guarda abaixo). */
    private const string TEST_PASSWORD = 'QaTeste@123';

    /** Slugs das organizações deste seeder — usados para reconhecer, na guarda abaixo, o que já foi criado por ele mesmo. */
    private const array ORGANIZATION_SLUGS = ['qa-alfa', 'qa-beta'];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('QaDatasetSeeder bloqueado em produção.');

            return;
        }

        // Instalação single-tenant (ADR-010): o front público e o
        // autocadastro do portal do paciente resolvem "a" organização da
        // instalação via App\Queries\CurrentInstallationOrganizationQuery,
        // assumindo que existe no máximo uma. Rodar este seeder de QA (que
        // sempre cria 2 organizações) numa base que já tem qualquer outra
        // organização "real" (demonstração/produção) quebra essa premissa
        // e faz o site público servir dados da organização errada — por
        // isso a guarda abaixo recusa rodar nesse cenário, em vez de só
        // avisar.
        $foreignOrganizations = Organization::query()->whereNotIn('slug', self::ORGANIZATION_SLUGS)->exists();

        if ($foreignOrganizations) {
            $this->command->error(
                'QaDatasetSeeder recusado: já existe pelo menos uma organização nesta base fora de '.implode('/', self::ORGANIZATION_SLUGS).
                '. Este seeder cria organizações de teste que quebram a premissa de instalação single-tenant '.
                '(ADR-010) usada pelo site público — rode-o só numa base dedicada a QA, nunca junto de dados de demonstração/produção.',
            );

            return;
        }

        $this->seedOrganization('Clínica Alfa QA', 'qa-alfa');
        $this->seedOrganization('Clínica Beta QA', 'qa-beta');

        $this->command->info('Massa de dados de QA (2 organizações) pronta — ver SEEDERS.md.');
    }

    private function seedOrganization(string $name, string $slug): void
    {
        $organization = Organization::query()->where('slug', $slug)->first()
            ?? Organization::factory()->create(['name' => $name, 'slug' => $slug]);

        $legalEntity = $organization->primaryLegalEntity()->first()
            ?? LegalEntity::factory()->primary()->for($organization)->create();

        $units = $this->seedUnits($organization, $legalEntity);
        $headquarters = $units->firstWhere('is_headquarters', true) ?? $units->first();

        app(SeedSystemRolesAction::class)->handle($organization);
        $this->seedStaffUsers($organization, $slug, $headquarters);

        $specialties = $this->seedSpecialties($organization);
        $services = $this->seedServices($organization, $specialties);
        $this->seedProducts($organization);
        $professionals = $this->seedProfessionals($organization, $slug, $headquarters, $specialties, $services);
        $patients = $this->seedPatients($organization, $headquarters);
        $appointments = $this->seedAppointments($organization, $headquarters, $professionals, $patients, $services);

        $completedAppointments = $appointments->where('status', AppointmentStatus::Completed)->values();
        $this->seedMedicalRecordsAndSales($organization, $headquarters, $legalEntity, $completedAppointments);

        $this->seedAppointmentRequests($organization, $headquarters, $professionals, $services);
        $this->seedWaitlistEntries($organization, $headquarters, $professionals, $patients, $services);
    }

    /** @return Collection<int, Unit> */
    private function seedUnits(Organization $organization, LegalEntity $legalEntity): Collection
    {
        $existing = Unit::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create([
            'name' => 'Unidade Matriz',
        ]);

        foreach (['Unidade Norte', 'Unidade Sul', 'Unidade Leste', 'Unidade Oeste'] as $branchName) {
            Unit::factory()->for($organization)->for($legalEntity, 'legalEntity')->create([
                'name' => $branchName,
            ]);
        }

        return Unit::query()->where('organization_id', $organization->id)->get();
    }

    private function seedStaffUsers(Organization $organization, string $slug, Unit $headquarters): void
    {
        if (OrganizationMembership::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        $roles = Role::query()->where('organization_id', $organization->id)->get()->keyBy('slug');

        $staff = [
            ['role' => SystemRole::Owner, 'label' => 'proprietaria', 'name' => 'Proprietária de Teste', 'isOwner' => true, 'isManager' => true],
            ['role' => SystemRole::ClinicAdmin, 'label' => 'admin', 'name' => 'Administradora de Teste', 'isOwner' => false, 'isManager' => true],
            ['role' => SystemRole::UnitManager, 'label' => 'gerente', 'name' => 'Gerente de Teste', 'isOwner' => false, 'isManager' => true],
            ['role' => SystemRole::Reception, 'label' => 'recepcao', 'name' => 'Recepcionista de Teste', 'isOwner' => false, 'isManager' => false],
            ['role' => SystemRole::Finance, 'label' => 'financeiro', 'name' => 'Financeiro de Teste', 'isOwner' => false, 'isManager' => false],
        ];

        foreach ($staff as $definition) {
            $email = "{$definition['label']}@{$slug}.qa.test";

            $user = User::query()->where('email', $email)->first() ?? User::factory()->create([
                'name' => $definition['name'],
                'email' => $email,
                'password' => Hash::make(self::TEST_PASSWORD),
            ]);

            $role = $roles->get($definition['role']->value);

            $membership = OrganizationMembership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'status' => OrganizationMembershipStatus::Active,
                'is_owner' => $definition['isOwner'],
                'role_id' => $role?->id,
                'joined_at' => now(),
                'created_by' => $user->id,
            ]);

            UnitMembership::query()->create([
                'organization_membership_id' => $membership->id,
                'unit_id' => $headquarters->id,
                'status' => RecordStatus::Active,
                'is_manager' => $definition['isManager'],
                'is_primary' => true,
            ]);
        }
    }

    /** @return Collection<int, Specialty> */
    private function seedSpecialties(Organization $organization): Collection
    {
        $existing = Specialty::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $names = [
            'Clínica geral',
            'Dermatologia',
            'Fisioterapia',
            'Nutrição',
            'Odontologia',
        ];

        foreach ($names as $order => $name) {
            Specialty::factory()->for($organization)->create([
                'name' => $name,
                'display_order' => $order,
            ]);
        }

        return Specialty::query()->where('organization_id', $organization->id)->get();
    }

    /**
     * @param  Collection<int, Specialty>  $specialties
     * @return Collection<int, Service>
     */
    private function seedServices(Organization $organization, Collection $specialties): Collection
    {
        $existing = Service::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        foreach ($specialties as $specialty) {
            $service = Service::factory()->for($organization)->create([
                'name' => 'Consulta — '.$specialty->name,
                'is_public' => true,
            ]);

            ServiceSpecialty::factory()->for($service)->create([
                'organization_id' => $organization->id,
                'specialty_id' => $specialty->id,
            ]);
        }

        return Service::query()->where('organization_id', $organization->id)->get();
    }

    private function seedProducts(Organization $organization): void
    {
        if (Product::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        Product::factory()->for($organization)->count(5)->create();
    }

    /**
     * @param  Collection<int, Specialty>  $specialties
     * @param  Collection<int, Service>  $services
     * @return Collection<int, Professional>
     */
    private function seedProfessionals(
        Organization $organization,
        string $slug,
        Unit $headquarters,
        Collection $specialties,
        Collection $services,
    ): Collection {
        $existing = Professional::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $specialtyPool = $specialties->values();
        $servicesBySpecialty = ServiceSpecialty::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->groupBy('specialty_id');

        $weekdays = array_slice(Weekday::inDisplayOrder(), 0, 5);

        for ($i = 0; $i < 5; $i++) {
            $email = "profissional{$i}@{$slug}.qa.test";
            $user = User::factory()->create([
                'email' => $email,
                'password' => Hash::make(self::TEST_PASSWORD),
            ]);

            $professional = Professional::factory()->for($organization)->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->name,
            ]);

            ProfessionalRegistration::factory()->primary()->for($professional)->create([
                'organization_id' => $organization->id,
            ]);

            $specialty = $specialtyPool[$i % $specialtyPool->count()];

            ProfessionalSpecialty::factory()->primary()->for($professional)->create([
                'organization_id' => $organization->id,
                'specialty_id' => $specialty->id,
            ]);

            foreach ($servicesBySpecialty->get($specialty->id, collect()) as $serviceSpecialty) {
                ProfessionalService::factory()->for($professional)->create([
                    'organization_id' => $organization->id,
                    'service_id' => $serviceSpecialty->service_id,
                ]);
            }

            $professionalUnit = ProfessionalUnit::factory()->primary()->for($professional)->create([
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
            ]);

            foreach ($weekdays as $weekday) {
                ProfessionalWorkingHour::factory()->for($professionalUnit)->create([
                    'organization_id' => $organization->id,
                    'weekday' => $weekday,
                    'starts_at' => '08:00',
                    'ends_at' => '18:00',
                ]);
            }
        }

        return Professional::query()->where('organization_id', $organization->id)->get();
    }

    /** @return Collection<int, Patient> */
    private function seedPatients(Organization $organization, Unit $headquarters): Collection
    {
        $existing = Patient::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $adults = Patient::factory()
            ->count(5)
            ->for($organization)
            ->create(['preferred_unit_id' => $headquarters->id]);

        $minor = Patient::factory()
            ->minor()
            ->for($organization)
            ->create(['preferred_unit_id' => $headquarters->id]);

        PatientResponsible::factory()->for($minor)->legalGuardian()->financialResponsible()->create([
            'organization_id' => $organization->id,
        ]);

        $allPatients = $adults->push($minor);

        // RN de negócio deste seeder (item de QA): TODO paciente recebe
        // contato de emergência, não apenas uma amostra — diferente do
        // padrão parcial de DemoOperationalDataSeeder.
        foreach ($allPatients as $patient) {
            PatientEmergencyContact::factory()->for($patient)->create([
                'organization_id' => $organization->id,
            ]);
        }

        return Patient::query()->where('organization_id', $organization->id)->get();
    }

    /**
     * @param  Collection<int, Professional>  $professionals
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Service>  $services
     * @return Collection<int, Appointment>
     */
    private function seedAppointments(
        Organization $organization,
        Unit $headquarters,
        Collection $professionals,
        Collection $patients,
        Collection $services,
    ): Collection {
        $existing = Appointment::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $professionalPool = $professionals->values();
        $patientPool = $patients->values();
        $servicePool = $services->values();

        $created = collect();
        $slot = 0;

        $pick = function (Collection $pool, int $index) {
            return $pool[$index % $pool->count()];
        };

        $baseAttributes = function (int $index) use ($professionalPool, $patientPool, $servicePool, $pick, $organization, $headquarters) {
            $service = $pick($servicePool, $index);

            return [
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
                'professional_id' => $pick($professionalPool, $index)->id,
                'patient_id' => $pick($patientPool, $index)->id,
                'service_id' => $service->id,
                'service' => $service,
            ];
        };

        // Concluídos no passado — base para prontuários/vendas.
        for ($i = 0; $i < 5; $i++) {
            $attributes = $baseAttributes($slot++);
            $service = $attributes['service'];
            unset($attributes['service']);

            $startsAt = now()->subDays(2 + $i)->setTime(9, 0);

            $created->push(Appointment::factory()->completed()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->addMinutes($service->default_duration_minutes),
            ]));
        }

        // Confirmados no futuro.
        for ($i = 0; $i < 5; $i++) {
            $attributes = $baseAttributes($slot++);
            $service = $attributes['service'];
            unset($attributes['service']);

            $startsAt = now()->addDays(1 + $i)->setTime(9, 0);

            $created->push(Appointment::factory()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->addMinutes($service->default_duration_minutes),
            ]));
        }

        // Cancelados.
        for ($i = 0; $i < 3; $i++) {
            $attributes = $baseAttributes($slot++);
            $service = $attributes['service'];
            unset($attributes['service']);

            $startsAt = now()->addDays(15 + $i)->setTime(9, 0);

            $created->push(Appointment::factory()->cancelled()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->addMinutes($service->default_duration_minutes),
            ]));
        }

        // Faltas (no-show).
        for ($i = 0; $i < 2; $i++) {
            $attributes = $baseAttributes($slot++);
            $service = $attributes['service'];
            unset($attributes['service']);

            $startsAt = now()->subDays(20 + $i)->setTime(9, 0);

            $created->push(Appointment::factory()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->addMinutes($service->default_duration_minutes),
                'status' => AppointmentStatus::NoShow,
            ]));
        }

        return $created;
    }

    /** @param  Collection<int, Appointment>  $completedAppointments */
    private function seedMedicalRecordsAndSales(
        Organization $organization,
        Unit $headquarters,
        LegalEntity $legalEntity,
        Collection $completedAppointments,
    ): void {
        if (MedicalRecord::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        $creator = User::query()
            ->whereHas('organizationMemberships', fn ($query) => $query->where('organization_id', $organization->id))
            ->first();

        foreach ($completedAppointments as $index => $appointment) {
            MedicalRecord::factory()->for($appointment)->finalized()->create();

            $service = Service::query()->findOrFail($appointment->service_id);
            $unitPriceCents = $service->default_price_cents ?? 10000;
            $discountCents = $index === 0 ? (int) round($unitPriceCents * 0.1) : 0;

            $sale = Sale::factory()->confirmed()->create([
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
                'legal_entity_id' => $legalEntity->id,
                'patient_id' => $appointment->patient_id,
                'professional_id' => $appointment->professional_id,
                'appointment_id' => $appointment->id,
                'subtotal_cents' => $unitPriceCents,
                'discount_total_cents' => $discountCents,
                'total_cents' => $unitPriceCents - $discountCents,
                'created_by' => $creator->id,
            ]);

            SaleItem::factory()->for($sale)->create([
                'organization_id' => $organization->id,
                'item_type' => SaleItemType::Service,
                'service_id' => $appointment->service_id,
                'product_id' => null,
                'unit_price_cents' => $unitPriceCents,
                'discount_percentage' => $discountCents > 0 ? 10 : 0,
                'final_price_cents' => $unitPriceCents - $discountCents,
            ]);
        }

        // Venda cancelada — representa o caso de estorno/cancelamento
        // exigido pela tarefa: este domínio não tem status "estornado"
        // dedicado (ver App\Enums\SaleStatus), então uma venda confirmada
        // cancelada é o equivalente suportado.
        $product = Product::query()->where('organization_id', $organization->id)->first();
        $productPriceCents = $product === null ? 5000 : $product->price_cents;

        $cancelledSale = Sale::factory()->cancelled()->create([
            'organization_id' => $organization->id,
            'unit_id' => $headquarters->id,
            'legal_entity_id' => $legalEntity->id,
            'patient_id' => $completedAppointments->first()->patient_id,
            'professional_id' => null,
            'appointment_id' => null,
            'subtotal_cents' => $productPriceCents,
            'discount_total_cents' => 0,
            'total_cents' => $productPriceCents,
            'created_by' => $creator->id,
        ]);

        SaleItem::factory()->for($cancelledSale)->create([
            'organization_id' => $organization->id,
            'item_type' => SaleItemType::Product,
            'service_id' => null,
            'product_id' => $product?->id,
            'unit_price_cents' => $productPriceCents,
            'final_price_cents' => $productPriceCents,
        ]);
    }

    /**
     * @param  Collection<int, Professional>  $professionals
     * @param  Collection<int, Service>  $services
     */
    private function seedAppointmentRequests(
        Organization $organization,
        Unit $headquarters,
        Collection $professionals,
        Collection $services,
    ): void {
        if (AppointmentRequest::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        $professionalPool = $professionals->values();
        $servicePool = $services->values();

        $statuses = [
            AppointmentRequestStatus::Pending,
            AppointmentRequestStatus::Pending,
            AppointmentRequestStatus::Contacted,
            AppointmentRequestStatus::Scheduled,
            AppointmentRequestStatus::Cancelled,
        ];

        foreach ($statuses as $index => $status) {
            AppointmentRequest::factory()->create([
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
                'preferred_service_id' => $servicePool[$index % $servicePool->count()]->id,
                'professional_id' => $professionalPool[$index % $professionalPool->count()]->id,
                'status' => $status,
            ]);
        }
    }

    /**
     * @param  Collection<int, Professional>  $professionals
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Service>  $services
     */
    private function seedWaitlistEntries(
        Organization $organization,
        Unit $headquarters,
        Collection $professionals,
        Collection $patients,
        Collection $services,
    ): void {
        if (WaitlistEntry::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        $professionalPool = $professionals->values();
        $servicePool = $services->values();
        $patientPool = $patients->values();

        for ($i = 0; $i < 5; $i++) {
            WaitlistEntry::factory()->create([
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
                'professional_id' => $i % 2 === 0 ? $professionalPool[$i % $professionalPool->count()]->id : null,
                'service_id' => $servicePool[$i % $servicePool->count()]->id,
                'patient_id' => $patientPool[$i % $patientPool->count()]->id,
                'preferred_date' => now()->addDays(($i + 1) * 3)->toDateString(),
                'status' => $i === 4 ? WaitlistEntryStatus::Cancelled : WaitlistEntryStatus::Waiting,
            ]);
        }
    }
}
