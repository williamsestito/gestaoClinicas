<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\SaleItemType;
use App\Enums\WaitlistEntryStatus;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\MedicalRecord;
use App\Models\Organization;
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
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceSpecialty;
use App\Models\Specialty;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Ambiente local de demonstração: popula o lado OPERACIONAL do sistema
 * (especialidades, serviços, produtos, profissionais, pacientes,
 * agendamentos em vários estados, vendas, prontuários e o funil vindo do
 * site público) para a organização/unidade matriz criadas por
 * DemoOrganizationSeeder — nunca a landing pública, já coberta por
 * LandingContentDemoSeeder.
 *
 * Idempotente por coleção (mesmo padrão de LandingContentDemoSeeder): cada
 * etapa verifica se já existe algo daquele tipo para a organização antes de
 * criar, então rodar o seeder várias vezes nunca duplica dados. Bloqueado em
 * produção.
 */
class DemoOperationalDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('DemoOperationalDataSeeder bloqueado em produção.');

            return;
        }

        $organization = Organization::query()->first();

        if (! $organization) {
            $this->command->warn('Nenhuma organização de demonstração encontrada — execute DemoOrganizationSeeder antes.');

            return;
        }

        $headquarters = $organization->headquarters()->first();

        if (! $headquarters) {
            $this->command->warn('A organização de demonstração não tem unidade matriz — execute DemoOrganizationSeeder antes.');

            return;
        }

        $legalEntity = $organization->primaryLegalEntity()->first()
            ?? LegalEntity::query()->where('organization_id', $organization->id)->first()
            ?? LegalEntity::factory()->primary()->for($organization)->create();

        $specialties = $this->seedSpecialties($organization);
        $services = $this->seedServices($organization, $specialties);
        $this->seedProducts($organization);
        $professionals = $this->seedProfessionals($organization, $headquarters, $specialties);
        $patients = $this->seedPatients($organization, $headquarters);
        $appointments = $this->seedAppointments($organization, $headquarters, $professionals, $patients, $services);

        $completedAppointments = $appointments->where('status', AppointmentStatus::Completed)->values();
        $this->seedMedicalRecordsAndSales($organization, $headquarters, $legalEntity, $completedAppointments);

        $this->seedAppointmentRequests($organization, $headquarters, $professionals, $services);
        $this->seedWaitlistEntries($organization, $headquarters, $professionals, $patients, $services);

        $this->command->info('Dados operacionais de demonstração prontos.');
    }

    /** @return Collection<int, Specialty> */
    private function seedSpecialties(Organization $organization): Collection
    {
        $existing = Specialty::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $names = [
            'Dermatologia clínica',
            'Fisioterapia ortopédica',
            'Nutrição clínica',
            'Estética facial e corporal',
            'Odontologia geral',
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

        $items = [
            ['name' => 'Consulta dermatológica', 'duration' => 30, 'price' => 25000, 'specialty' => 'Dermatologia clínica'],
            ['name' => 'Peeling químico', 'duration' => 30, 'price' => 35000, 'specialty' => 'Dermatologia clínica'],
            ['name' => 'Avaliação fisioterapêutica', 'duration' => 45, 'price' => 18000, 'specialty' => 'Fisioterapia ortopédica'],
            ['name' => 'Sessão de fisioterapia', 'duration' => 45, 'price' => 12000, 'specialty' => 'Fisioterapia ortopédica'],
            ['name' => 'Consulta nutricional', 'duration' => 45, 'price' => 20000, 'specialty' => 'Nutrição clínica'],
            ['name' => 'Limpeza de pele profunda', 'duration' => 60, 'price' => 15000, 'specialty' => 'Estética facial e corporal'],
            ['name' => 'Avaliação odontológica', 'duration' => 30, 'price' => 15000, 'specialty' => 'Odontologia geral'],
            ['name' => 'Profilaxia dental', 'duration' => 60, 'price' => 18000, 'specialty' => 'Odontologia geral'],
        ];

        $specialtiesByName = $specialties->keyBy('name');

        foreach ($items as $item) {
            $service = Service::factory()->for($organization)->create([
                'name' => $item['name'],
                'default_duration_minutes' => $item['duration'],
                'default_price_cents' => $item['price'],
                'is_public' => true,
            ]);

            $specialty = $specialtiesByName->get($item['specialty']);

            if ($specialty) {
                ServiceSpecialty::factory()->for($service)->create([
                    'organization_id' => $organization->id,
                    'specialty_id' => $specialty->id,
                ]);
            }
        }

        return Service::query()->where('organization_id', $organization->id)->get();
    }

    private function seedProducts(Organization $organization): void
    {
        if (Product::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        $items = [
            ['name' => 'Protetor solar FPS 60', 'cost' => 1800, 'price' => 4900],
            ['name' => 'Sérum vitamina C', 'cost' => 3200, 'price' => 8900],
            ['name' => 'Creme hidratante facial', 'cost' => 2200, 'price' => 6900],
            ['name' => 'Kit de eletrodos para fisioterapia', 'cost' => 1500, 'price' => 3900],
            ['name' => 'Suplemento vitamínico', 'cost' => 2500, 'price' => 5900],
            ['name' => 'Enxaguante bucal', 'cost' => 900, 'price' => 2900],
        ];

        foreach ($items as $item) {
            Product::factory()->for($organization)->create([
                'name' => $item['name'],
                'cost_cents' => $item['cost'],
                'price_cents' => $item['price'],
            ]);
        }
    }

    /**
     * @param  Collection<int, Specialty>  $specialties
     * @return Collection<int, Professional>
     */
    private function seedProfessionals(
        Organization $organization,
        Unit $headquarters,
        Collection $specialties,
    ): Collection {
        $existing = Professional::query()->where('organization_id', $organization->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $definitions = [
            ['name' => 'Dra. Camila Andrade', 'specialty' => 'Dermatologia clínica', 'council' => 'CRM'],
            ['name' => 'Dr. Rafael Souza', 'specialty' => 'Fisioterapia ortopédica', 'council' => 'CREFITO'],
            ['name' => 'Dra. Fernanda Lima', 'specialty' => 'Nutrição clínica', 'council' => 'CRN'],
            ['name' => 'Dra. Patrícia Nogueira', 'specialty' => 'Estética facial e corporal', 'council' => null],
            ['name' => 'Dr. Eduardo Martins', 'specialty' => 'Odontologia geral', 'council' => 'CRO'],
        ];

        $specialtiesByName = $specialties->keyBy('name');
        $servicesBySpecialty = ServiceSpecialty::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->groupBy('specialty_id');

        $weekdays = array_slice(Weekday::inDisplayOrder(), 0, 5);

        foreach ($definitions as $definition) {
            $user = User::factory()->create(['name' => $definition['name']]);

            $professional = Professional::factory()->for($organization)->create([
                'user_id' => $user->id,
                'name' => $definition['name'],
                'display_name' => $definition['name'],
            ]);

            ProfessionalRegistration::factory()->primary()->for($professional)->create([
                'organization_id' => $organization->id,
                ...($definition['council'] !== null ? ['council' => $definition['council']] : []),
            ]);

            $specialty = $specialtiesByName->get($definition['specialty']);

            if ($specialty) {
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
            ->count(17)
            ->for($organization)
            ->create(['preferred_unit_id' => $headquarters->id]);

        $minors = Patient::factory()
            ->minor()
            ->count(3)
            ->for($organization)
            ->create(['preferred_unit_id' => $headquarters->id]);

        foreach ($minors as $minor) {
            PatientResponsible::factory()->for($minor)->legalGuardian()->financialResponsible()->create([
                'organization_id' => $organization->id,
            ]);
        }

        foreach ($adults->take(6) as $adult) {
            PatientEmergencyContact::factory()->for($adult)->create([
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

        // Atendimentos concluídos no passado — base para prontuários/vendas.
        for ($i = 0; $i < 12; $i++) {
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

        // Agendamentos confirmados no futuro.
        for ($i = 0; $i < 10; $i++) {
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

        // Agendamentos cancelados.
        for ($i = 0; $i < 4; $i++) {
            $attributes = $baseAttributes($slot++);
            $service = $attributes['service'];
            unset($attributes['service']);

            $startsAt = now()->addDays(15 + $i)->setTime(9, 0);

            $created->push(Appointment::factory()->cancelled()->create([
                ...$attributes,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->clone()->addMinutes($service->default_duration_minutes),
                'cancellation_reason' => 'Cancelado a pedido do paciente.',
            ]));
        }

        // Faltas (no-show) no passado.
        for ($i = 0; $i < 4; $i++) {
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

        $creator = User::query()->first() ?? User::factory()->create();

        foreach ($completedAppointments as $index => $appointment) {
            MedicalRecord::factory()->for($appointment)->finalized()->create();

            if ($index >= 8) {
                continue;
            }

            $service = Service::query()->findOrFail($appointment->service_id);
            $unitPriceCents = $service->default_price_cents ?? 10000;

            $sale = Sale::factory()->confirmed()->create([
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
                'legal_entity_id' => $legalEntity->id,
                'patient_id' => $appointment->patient_id,
                'professional_id' => $appointment->professional_id,
                'appointment_id' => $appointment->id,
                'subtotal_cents' => $unitPriceCents,
                'discount_total_cents' => 0,
                'total_cents' => $unitPriceCents,
                'created_by' => $creator->id,
            ]);

            SaleItem::factory()->for($sale)->create([
                'organization_id' => $organization->id,
                'item_type' => SaleItemType::Service,
                'service_id' => $appointment->service_id,
                'product_id' => null,
                'unit_price_cents' => $unitPriceCents,
                'final_price_cents' => $unitPriceCents,
            ]);

            if ($index % 3 !== 0) {
                continue;
            }

            $product = Product::query()->where('organization_id', $organization->id)->inRandomOrder()->first();

            if (! $product) {
                continue;
            }

            SaleItem::factory()->for($sale)->create([
                'organization_id' => $organization->id,
                'item_type' => SaleItemType::Product,
                'service_id' => null,
                'product_id' => $product->id,
                'unit_price_cents' => $product->price_cents,
                'final_price_cents' => $product->price_cents,
            ]);

            $sale->update([
                'subtotal_cents' => $sale->subtotal_cents + $product->price_cents,
                'total_cents' => $sale->total_cents + $product->price_cents,
            ]);
        }
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

        for ($i = 1; $i <= 4; $i++) {
            WaitlistEntry::factory()->create([
                'organization_id' => $organization->id,
                'unit_id' => $headquarters->id,
                'professional_id' => $i % 2 === 0 ? $professionalPool[$i % $professionalPool->count()]->id : null,
                'service_id' => $servicePool[$i % $servicePool->count()]->id,
                'patient_id' => $patients->random()->id,
                'preferred_date' => now()->addDays($i * 3)->toDateString(),
                'status' => $i === 4 ? WaitlistEntryStatus::Cancelled : WaitlistEntryStatus::Waiting,
            ]);
        }
    }
}
