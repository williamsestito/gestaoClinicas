<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Professional;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\WaitlistEntry;
use Database\Seeders\DemoOperationalDataSeeder;
use Database\Seeders\DemoOrganizationSeeder;

it('does nothing in production', function () {
    app()['env'] = 'production';

    // --force evita o prompt de confirmacao do proprio comando `db:seed`
    // em producao (nivel Artisan) — o que estamos testando aqui e a
    // guarda interna do seeder, nao esse prompt.
    $this->artisan('db:seed', ['--class' => DemoOperationalDataSeeder::class, '--force' => true]);

    expect(Patient::query()->count())->toBe(0)
        ->and(Specialty::query()->count())->toBe(0);

    app()['env'] = 'testing';
});

it('does nothing when there is no demo organization yet', function () {
    $this->seed(DemoOperationalDataSeeder::class);

    expect(Organization::query()->count())->toBe(0)
        ->and(Patient::query()->count())->toBe(0)
        ->and(Specialty::query()->count())->toBe(0);
});

it('creates a coherent set of operational data for the demo organization', function () {
    $this->seed(DemoOrganizationSeeder::class);
    $this->seed(DemoOperationalDataSeeder::class);

    $organization = Organization::query()->first();
    expect($organization)->not->toBeNull();

    expect(Specialty::query()->where('organization_id', $organization->id)->count())->toBeGreaterThan(0)
        ->and(Service::query()->where('organization_id', $organization->id)->count())->toBeGreaterThan(0)
        ->and(Product::query()->where('organization_id', $organization->id)->count())->toBeGreaterThan(0)
        ->and(Professional::query()->where('organization_id', $organization->id)->count())->toBe(5)
        ->and(Patient::query()->where('organization_id', $organization->id)->count())->toBe(20)
        ->and(Appointment::query()->where('organization_id', $organization->id)->count())->toBe(30)
        ->and(MedicalRecord::query()->where('organization_id', $organization->id)->count())->toBe(12)
        ->and(Sale::query()->where('organization_id', $organization->id)->count())->toBe(8)
        ->and(SaleItem::query()->where('organization_id', $organization->id)->count())->toBeGreaterThanOrEqual(8)
        ->and(AppointmentRequest::query()->where('organization_id', $organization->id)->count())->toBe(6)
        ->and(WaitlistEntry::query()->where('organization_id', $organization->id)->count())->toBe(4);

    // Cada profissional tem exatamente um vínculo de unidade principal e
    // uma jornada semanal (segunda a sexta) associada a esse vínculo.
    $professionalIds = Professional::query()->where('organization_id', $organization->id)->pluck('id');

    foreach ($professionalIds as $professionalId) {
        $primaryLinks = ProfessionalUnit::query()
            ->where('professional_id', $professionalId)
            ->where('is_primary', true)
            ->get();

        expect($primaryLinks)->toHaveCount(1);

        expect(ProfessionalWorkingHour::query()->where('professional_unit_id', $primaryLinks->first()->id)->count())->toBe(5);
    }

    // Agendamentos cobrem os estados variados esperados.
    $statuses = Appointment::query()
        ->where('organization_id', $organization->id)
        ->pluck('status')
        ->countBy(fn (AppointmentStatus $status) => $status->value);

    expect($statuses->get(AppointmentStatus::Completed->value))->toBe(12)
        ->and($statuses->get(AppointmentStatus::Confirmed->value))->toBe(10)
        ->and($statuses->get(AppointmentStatus::Cancelled->value))->toBe(4)
        ->and($statuses->get(AppointmentStatus::NoShow->value))->toBe(4);
});

it('is idempotent — running it twice does not create duplicate records', function () {
    $this->seed(DemoOrganizationSeeder::class);
    $this->seed(DemoOperationalDataSeeder::class);

    $organization = Organization::query()->first();

    $counts = [
        'specialties' => Specialty::query()->where('organization_id', $organization->id)->count(),
        'services' => Service::query()->where('organization_id', $organization->id)->count(),
        'products' => Product::query()->where('organization_id', $organization->id)->count(),
        'professionals' => Professional::query()->where('organization_id', $organization->id)->count(),
        'patients' => Patient::query()->where('organization_id', $organization->id)->count(),
        'appointments' => Appointment::query()->where('organization_id', $organization->id)->count(),
        'medical_records' => MedicalRecord::query()->where('organization_id', $organization->id)->count(),
        'sales' => Sale::query()->where('organization_id', $organization->id)->count(),
        'appointment_requests' => AppointmentRequest::query()->where('organization_id', $organization->id)->count(),
        'waitlist_entries' => WaitlistEntry::query()->where('organization_id', $organization->id)->count(),
    ];

    $this->seed(DemoOperationalDataSeeder::class);

    expect(Specialty::query()->where('organization_id', $organization->id)->count())->toBe($counts['specialties'])
        ->and(Service::query()->where('organization_id', $organization->id)->count())->toBe($counts['services'])
        ->and(Product::query()->where('organization_id', $organization->id)->count())->toBe($counts['products'])
        ->and(Professional::query()->where('organization_id', $organization->id)->count())->toBe($counts['professionals'])
        ->and(Patient::query()->where('organization_id', $organization->id)->count())->toBe($counts['patients'])
        ->and(Appointment::query()->where('organization_id', $organization->id)->count())->toBe($counts['appointments'])
        ->and(MedicalRecord::query()->where('organization_id', $organization->id)->count())->toBe($counts['medical_records'])
        ->and(Sale::query()->where('organization_id', $organization->id)->count())->toBe($counts['sales'])
        ->and(AppointmentRequest::query()->where('organization_id', $organization->id)->count())->toBe($counts['appointment_requests'])
        ->and(WaitlistEntry::query()->where('organization_id', $organization->id)->count())->toBe($counts['waitlist_entries']);
});
