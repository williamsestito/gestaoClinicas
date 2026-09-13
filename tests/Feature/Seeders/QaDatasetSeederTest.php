<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\PatientEmergencyContact;
use App\Models\PatientResponsible;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\QaDatasetSeeder;

it('does nothing in production', function () {
    app()['env'] = 'production';

    $this->artisan('db:seed', ['--class' => QaDatasetSeeder::class, '--force' => true]);

    expect(Organization::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);

    app()['env'] = 'testing';
});

it('refuses to run when a foreign organization already exists, to never break the single-tenant public site (ADR-010)', function () {
    $realOrganization = Organization::factory()->create(['name' => 'Espaço Duda Almeida']);

    $this->artisan('db:seed', ['--class' => QaDatasetSeeder::class, '--force' => true]);

    expect(Organization::query()->count())->toBe(1)
        ->and(Organization::query()->first()->id)->toBe($realOrganization->id)
        ->and(User::query()->count())->toBe(0);
});

it('creates two fully independent organizations with a coherent, non-mixed dataset each', function () {
    $this->seed(QaDatasetSeeder::class);

    $orgs = Organization::query()->whereIn('slug', ['qa-alfa', 'qa-beta'])->get()->keyBy('slug');

    expect($orgs)->toHaveCount(2);

    foreach (['qa-alfa', 'qa-beta'] as $slug) {
        $organization = $orgs->get($slug);

        expect($organization)->not->toBeNull();
        expect(Unit::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(OrganizationMembership::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(Specialty::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(Service::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(Product::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(Professional::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(Patient::query()->where('organization_id', $organization->id)->count())->toBe(6);
        expect(Appointment::query()->where('organization_id', $organization->id)->count())->toBe(15);
        expect(MedicalRecord::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(Sale::query()->where('organization_id', $organization->id)->count())->toBe(6);
        expect(AppointmentRequest::query()->where('organization_id', $organization->id)->count())->toBe(5);
        expect(WaitlistEntry::query()->where('organization_id', $organization->id)->count())->toBe(5);

        // RN-004: nenhum paciente menor de idade sem responsável legal ativo.
        $minorCount = Patient::query()->where('organization_id', $organization->id)->whereNull('document')->count();
        expect($minorCount)->toBe(1)
            ->and(PatientResponsible::query()->where('organization_id', $organization->id)->count())->toBe($minorCount);

        // Todo paciente (não só uma amostra) recebe contato de emergência.
        expect(PatientEmergencyContact::query()->where('organization_id', $organization->id)->count())->toBe(6);

        // Agendamentos cobrem pelo menos 3 estados diferentes.
        $statuses = Appointment::query()->where('organization_id', $organization->id)->pluck('status')->unique();
        expect($statuses->count())->toBeGreaterThanOrEqual(3)
            ->and($statuses->contains(AppointmentStatus::Completed))->toBeTrue();

        // Vendas cobrem desconto e cancelamento (equivalente a estorno — ver App\Enums\SaleStatus).
        expect(Sale::query()->where('organization_id', $organization->id)->where('discount_total_cents', '>', 0)->exists())->toBeTrue();
        expect(Sale::query()->where('organization_id', $organization->id)->where('status', 'cancelled')->exists())->toBeTrue();
    }

    // Nada se mistura entre as duas organizações.
    $alfaPatientIds = Patient::query()->where('organization_id', $orgs->get('qa-alfa')->id)->pluck('id');
    $betaPatientIds = Patient::query()->where('organization_id', $orgs->get('qa-beta')->id)->pluck('id');
    expect($alfaPatientIds->intersect($betaPatientIds))->toBeEmpty();

    $alfaAppointmentPatientIds = Appointment::query()->where('organization_id', $orgs->get('qa-alfa')->id)->pluck('patient_id')->unique();
    expect($alfaAppointmentPatientIds->diff($alfaPatientIds))->toBeEmpty();
});

it('is idempotent — running it twice does not create duplicate records', function () {
    $this->seed(QaDatasetSeeder::class);

    $organization = Organization::query()->where('slug', 'qa-alfa')->firstOrFail();

    $counts = [
        'units' => Unit::query()->where('organization_id', $organization->id)->count(),
        'memberships' => OrganizationMembership::query()->where('organization_id', $organization->id)->count(),
        'professionals' => Professional::query()->where('organization_id', $organization->id)->count(),
        'patients' => Patient::query()->where('organization_id', $organization->id)->count(),
        'appointments' => Appointment::query()->where('organization_id', $organization->id)->count(),
        'sales' => Sale::query()->where('organization_id', $organization->id)->count(),
    ];

    $this->seed(QaDatasetSeeder::class);

    expect(Organization::query()->whereIn('slug', ['qa-alfa', 'qa-beta'])->count())->toBe(2)
        ->and(Unit::query()->where('organization_id', $organization->id)->count())->toBe($counts['units'])
        ->and(OrganizationMembership::query()->where('organization_id', $organization->id)->count())->toBe($counts['memberships'])
        ->and(Professional::query()->where('organization_id', $organization->id)->count())->toBe($counts['professionals'])
        ->and(Patient::query()->where('organization_id', $organization->id)->count())->toBe($counts['patients'])
        ->and(Appointment::query()->where('organization_id', $organization->id)->count())->toBe($counts['appointments'])
        ->and(Sale::query()->where('organization_id', $organization->id)->count())->toBe($counts['sales']);
});
