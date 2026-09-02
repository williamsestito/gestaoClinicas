<?php

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PatientUserLinkRole;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\Role;
use App\Models\Service;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Cria uma organização com entidade legal e unidade matriz, autentica um
 * usuário proprietário como membro ativo de ambas, e define o contexto de
 * organização/unidade ativos na sessão. Usado por testes que exercitam
 * rotas sob os middlewares de tenancy.
 */
function actingOwnerWithActiveContext(): User
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()
        ->owner()
        ->for($organization)
        ->for($user)
        ->create(['status' => OrganizationMembershipStatus::Active]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unit, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $unit->id]);

    return $user;
}

/**
 * Cria uma organização, o profissional autenticado (com um paciente próprio
 * e um atendimento concluído), pronta para exercitar prontuário clínico.
 * Compartilhada entre os testes de prontuário do staff
 * (tests/Feature/MedicalRecords) e do portal do paciente
 * (tests/Feature/PatientPortal/MedicalRecordVisibilityTest.php) — precisa
 * viver aqui, não solta num arquivo de teste específico, para funcionar
 * independente de qual arquivo o Pest carrega primeiro.
 *
 * @return array{organization: Organization, unit: Unit, professionalUser: User, professional: Professional, patient: Patient, appointment: Appointment}
 */
function medicalRecordSetup(): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    $unit = Unit::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $professionalUser = User::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['user_id' => $professionalUser->id, 'status' => RecordStatus::Active]);
    $membership = OrganizationMembership::factory()->for($organization)->for($professionalUser)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership)->create(['unit_id' => $unit->id, 'status' => RecordStatus::Active]);
    session(['active_organization_id' => $organization->id]);

    $patient = Patient::factory()->for($organization)->create(['primary_professional_id' => $professional->id]);
    $appointment = Appointment::factory()->completed()->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
    ]);

    return compact('organization', 'unit', 'professionalUser', 'professional', 'patient', 'appointment');
}

/**
 * Cria um usuário de staff ativo na organização com o papel de sistema
 * informado — usado pelos testes de prontuário para checar acesso de
 * colegas/recepção/financeiro. Companheira de medicalRecordSetup(), mesma
 * razão para viver aqui.
 */
function medicalRecordStaffUser(Organization $organization, SystemRole $role): User
{
    $roleModel = Role::query()->where('organization_id', $organization->id)->where('slug', $role->value)->firstOrFail();
    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $roleModel->id,
    ]);

    return $user;
}

/**
 * Organização própria com unidade matriz, entidade legal e proprietário
 * autenticado — sem papéis de sistema semeados (ver seedSystemRoles()
 * abaixo para isso). Compartilhada entre os testes de Organization/
 * Sales/Roles — precisa viver aqui, não solta num arquivo específico, para
 * funcionar independente de qual arquivo o Pest carrega primeiro.
 *
 * @return array{organization: Organization, legalEntity: LegalEntity, headquarters: Unit, user: User, membership: OrganizationMembership}
 */
function ownerActingInOrganization(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    return compact('organization', 'legalEntity', 'headquarters', 'user', 'membership');
}

/** Companheira de ownerActingInOrganization()/nonOwnerActingWithRole(), mesma razão para viver aqui. */
function seedSystemRoles(Organization $organization): void
{
    app(SeedSystemRolesAction::class)->handle($organization);
}

/**
 * @return array{user: User, membership: OrganizationMembership, role: Role}
 */
function nonOwnerActingWithRole(Organization $organization, SystemRole $systemRole): array
{
    seedSystemRoles($organization);

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', $systemRole->value)->firstOrFail();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create(['role_id' => $role->id]);

    session(['active_organization_id' => $organization->id]);

    return compact('user', 'membership', 'role');
}

/**
 * Organização própria com unidade (fuso America/Sao_Paulo, funcionamento
 * 08:00-18:00 às segundas), profissional com jornada 08:00-18:00 às
 * segundas, serviço de 30min sem buffer, e um paciente — pronta para testar
 * criação de agendamento real. Compartilhada entre os testes de Appointments
 * e PatientPortal — precisa viver aqui pela mesma razão das demais.
 */
function appointmentSetup(): array
{
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $unit = $organization->units()->first();
    $unit->update(['timezone' => 'America/Sao_Paulo']);
    $unit->openingHours()->create([
        'organization_id' => $organization->id,
        'day_of_week' => Weekday::Monday->value,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'sort_order' => 0,
    ]);

    $professional = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);
    $professionalUnit = ProfessionalUnit::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'status' => RecordStatus::Active,
    ]);
    ProfessionalWorkingHour::factory()->for($professionalUnit, 'professionalUnit')->create([
        'organization_id' => $organization->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '18:00',
    ]);

    $service = Service::factory()->for($organization)->create([
        'default_duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);
    $link = ProfessionalService::factory()->for($professional)->create([
        'organization_id' => $organization->id,
        'service_id' => $service->id,
    ]);

    $patient = Patient::factory()->for($organization)->create();

    return compact('user', 'organization', 'unit', 'professional', 'professionalUnit', 'service', 'link', 'patient');
}

// 2026-08-03 é uma segunda-feira.
function appointmentMonday(): Carbon
{
    return Carbon::parse('2026-08-03');
}

function createConfirmedAppointment(array $setup, ?Carbon $startsAt = null): Appointment
{
    // ->utc() é obrigatório: o cast 'datetime' do Eloquent serializa o
    // Carbon no fuso em que ele já está, sem converter sozinho (mesmo
    // cuidado do controller, ver AppointmentController::store()).
    $startsAt ??= Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->utc();

    return Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed,
    ]);
}

/** Companheira de appointmentSetup()/appointmentMonday(), mesma razão para viver aqui. */
function patientPortalAppointmentSetup(): array
{
    $setup = appointmentSetup();

    $patientUser = PatientUser::factory()->for($setup['organization'])->create();
    PatientUserLink::factory()
        ->for($patientUser)
        ->for($setup['patient'], 'patient')
        ->create(['organization_id' => $setup['organization']->id, 'role' => PatientUserLinkRole::Self]);

    return [...$setup, 'patientUser' => $patientUser];
}

/**
 * @return array{organization: Organization, patientUser: PatientUser, patient: Patient}
 */
function patientPortalProfileSetup(): array
{
    $organization = Organization::factory()->create();
    $patientUser = PatientUser::factory()->for($organization)->create();
    $patient = Patient::factory()->for($organization)->create();
    PatientUserLink::factory()->for($patientUser)->for($patient, 'patient')->create(['organization_id' => $organization->id]);

    return compact('organization', 'patientUser', 'patient');
}

/**
 * Autentica um usuário com o papel de sistema informado, ativo na
 * organização — sem cadastro de profissional vinculado (ver
 * professionalSearchSetup() para o caminho de autoatendimento).
 * Compartilhada entre os testes de busca e resumo de paciente — precisa
 * viver aqui pela mesma razão das demais.
 *
 * @return array{0: User, 1: Organization}
 */
function patientSearchStaffSetup(SystemRole $role): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $roleModel = Role::query()->where('organization_id', $organization->id)->where('slug', $role->value)->firstOrFail();

    $user = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $roleModel->id,
    ]);
    session(['active_organization_id' => $organization->id]);

    return [$user, $organization];
}

/**
 * Profissional autenticado com o papel de sistema `Professional`
 * (autoatendimento — só `patients.view-own`). Companheira de
 * patientSearchStaffSetup(), mesma razão para viver aqui.
 *
 * @return array{0: User, 1: Organization, 2: Professional}
 */
function professionalSearchSetup(): array
{
    [$user, $organization] = patientSearchStaffSetup(SystemRole::Professional);
    $professional = Professional::factory()->for($organization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);

    return [$user, $organization, $professional];
}

/**
 * @return array{organization: Organization, unit: Unit, legalEntity: LegalEntity, user: User, patient: Patient}
 */
function saleSetup(): array
{
    $organization = Organization::factory()->create();
    app(SeedSystemRolesAction::class)->handle($organization);

    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $unit = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();

    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::ClinicAdmin->value)->firstOrFail();
    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($unit, 'unit')->create();

    session(['active_organization_id' => $organization->id, 'active_unit_id' => $unit->id]);

    $patient = Patient::factory()->for($organization)->create();

    return compact('organization', 'unit', 'legalEntity', 'user', 'patient');
}
