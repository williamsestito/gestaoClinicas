<?php

declare(strict_types=1);

use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\PermissionKey;
use App\Enums\SaleStatus;
use App\Enums\SystemRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;

it('shows real user, unit and legal entity counts on the dashboard', function () {
    $ctx = ownerActingInOrganization();

    $inactiveMember = User::factory()->create(['is_active' => false]);
    OrganizationMembership::factory()->for($ctx['organization'])->for($inactiveMember)->create();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('unitsCount', 1)
            ->where('legalEntitiesCount', 1)
            ->where('usersCount', 2)
            ->where('activeUsersCount', 1)
            ->where('inactiveUsersCount', 1)
        );
});

it('lists pending setup items when domain and SEO are not configured', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('domainConfigured', false)
            ->where('seoConfigured', false)
            ->where('pendingSetupItems', fn ($items) => count($items) > 0)
        );
});

it('reports the domain and SEO as configured once SiteSetting has the data', function () {
    $ctx = ownerActingInOrganization();
    SiteSetting::factory()->create([
        'official_domain' => 'clinicaexemplo.com.br',
        'meta_title' => 'Clínica Exemplo',
        'meta_description' => 'Cuidando de você.',
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('domainConfigured', true)
            ->where('seoConfigured', true)
        );
});

it('groups pending appointment requests by professional for the admin/reception dashboard alert', function () {
    $ctx = ownerActingInOrganization();
    $professionalA = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    $professionalB = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dr João Paiva']);

    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professionalA->id, 'name' => 'Lead 1']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professionalA->id, 'name' => 'Lead 2']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professionalB->id, 'name' => 'Lead 3']);
    AppointmentRequest::factory()->contacted()->for($ctx['organization'])->create(['professional_id' => $professionalA->id, 'name' => 'Já contatado']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['status' => AppointmentRequestStatus::Cancelled, 'professional_id' => $professionalA->id, 'name' => 'Cancelado']);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendingAppointmentRequestsByProfessional', 2)
            ->where('pendingAppointmentRequestsByProfessional.0.professional_name', 'Dra Juliana Cruz')
            ->where('pendingAppointmentRequestsByProfessional.0.count', 2)
            ->where('pendingAppointmentRequestsByProfessional.1.professional_name', 'Dr João Paiva')
            ->where('pendingAppointmentRequestsByProfessional.1.count', 1));
});

it('falls back to a placeholder label instead of crashing when the requested professional was logically deleted', function () {
    $ctx = ownerActingInOrganization();
    $professional = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professional->id, 'name' => 'Lead 1']);
    $professional->delete();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendingAppointmentRequestsByProfessional', 1)
            ->where('pendingAppointmentRequestsByProfessional.0.professional_name', 'Sem profissional definido')
            ->where('pendingAppointmentRequestsByProfessional.0.count', 1));
});

it('never exposes the pending-appointment-request alert to a role without site.appointments.view', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    AppointmentRequest::factory()->for($ctx['organization'])->create();

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Finance->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingAppointmentRequestsByProfessional', null));
});

it('shows the pending-appointment-request alert to a reception user, for professionals other than themselves', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    $professional = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    AppointmentRequest::factory()->for($ctx['organization'])->create(['professional_id' => $professional->id]);

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Reception->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendingAppointmentRequestsByProfessional', 1)
            ->where('pendingAppointmentRequestsByProfessional.0.professional_name', 'Dra Juliana Cruz'));
});

it('shows today\'s organization-wide agenda, filterable by professional, for admin/reception', function () {
    $ctx = ownerActingInOrganization();
    $professionalA = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    $professionalB = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dr João Paiva']);
    Appointment::factory()->for($ctx['organization'])->for($professionalA)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->setTime(9, 0)]);
    Appointment::factory()->for($ctx['organization'])->for($professionalB)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->setTime(10, 0)]);
    Appointment::factory()->for($ctx['organization'])->for($professionalA)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => now()->addDay()->setTime(9, 0)]);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orgAgenda.appointments', 2)
            ->has('orgAgenda.professionals', 2));

    $this->actingAs($ctx['user'])
        ->get(route('dashboard', ['agenda_professional_id' => $professionalA->id]))
        ->assertInertia(fn ($page) => $page
            ->has('orgAgenda.appointments', 1)
            ->where('orgAgenda.appointments.0.professional_name', 'Dra Juliana Cruz'));
});

it('keeps showing the real historical names in the organization agenda after the professional/patient/service/unit was logically deleted', function () {
    $ctx = ownerActingInOrganization();
    $professional = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);
    $patient = Patient::factory()->for($ctx['organization'])->create(['name' => 'Ana Souza', 'preferred_name' => null]);
    $service = Service::factory()->for($ctx['organization'])->create(['name' => 'Limpeza de pele']);
    // Unidade própria (não a matriz do contexto ativo) — apagar a matriz
    // quebraria a resolução de tenant/sessão, o que não é o que este teste
    // quer exercitar.
    $unit = Unit::factory()->for($ctx['organization'])->create(['name' => 'Unidade Norte']);
    Appointment::factory()->for($ctx['organization'])->for($professional)->for($patient)->for($service)->for($unit, 'unit')->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->setTime(9, 0),
    ]);
    $professional->delete();
    $patient->delete();
    $service->delete();
    $unit->delete();

    // Achado real: acessar a agenda quebrava com 500 assim que qualquer um
    // desses vínculos era excluído logicamente — ver
    // App\Actions\Organization\DeleteProfessionalAction, cujo próprio
    // contrato é preservar o histórico, não escondê-lo.
    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orgAgenda.appointments.0.professional_name', 'Dra Juliana Cruz')
            ->where('orgAgenda.appointments.0.patient_name', 'Ana Souza')
            ->where('orgAgenda.appointments.0.service_name', 'Limpeza de pele')
            ->where('orgAgenda.appointments.0.unit_name', 'Unidade Norte'));
});

it('never exposes the organization agenda to a role without appointments.view', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);
    Appointment::factory()->for($ctx['organization'])->create(['status' => AppointmentStatus::Confirmed]);

    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Finance->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('orgAgenda', null));
});

it('shares only the permissions granted by the assigned role in the tenant prop', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    seedSystemRoles($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Reception->value)->firstOrFail();

    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('tenant.permissions', fn ($permissions) => collect($permissions)->contains(PermissionKey::UnitsView->value)
                && ! collect($permissions)->contains(PermissionKey::UsersInvite->value))
        );
});

it('no longer exposes a technical audit/activity log feed on the dashboard payload', function () {
    $ctx = ownerActingInOrganization();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->missing('recentActivity'));
});

it('shows the management indicators (today, pending confirmations, revenue, new patients) for an owner', function () {
    $ctx = ownerActingInOrganization();
    $professional = Professional::factory()->for($ctx['organization'])->create();
    // Reaproveitado nos agendamentos abaixo — evita que o factory de
    // Appointment crie pacientes próprios "escondidos", o que inflaria a
    // contagem de novos pacientes que este teste quer conferir com
    // precisão.
    $existingPatient = Patient::factory()->for($ctx['organization'])->create();

    Appointment::factory()->for($ctx['organization'])->for($professional)->for($existingPatient)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->setTime(9, 0),
    ]);
    Appointment::factory()->for($ctx['organization'])->for($professional)->for($existingPatient)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->addDay()->setTime(9, 0),
    ]);

    AppointmentRequest::factory()->for($ctx['organization'])->create();
    AppointmentRequest::factory()->for($ctx['organization'])->create();
    AppointmentRequest::factory()->contacted()->for($ctx['organization'])->create();

    Sale::factory()->create([
        'organization_id' => $ctx['organization']->id,
        'patient_id' => $existingPatient->id,
        'status' => SaleStatus::Confirmed,
        'total_cents' => 15000,
    ]);
    Sale::factory()->create([
        'organization_id' => $ctx['organization']->id,
        'patient_id' => $existingPatient->id,
        'status' => SaleStatus::Cancelled,
        'total_cents' => 99999,
    ]);

    // Único paciente que deve contar como "novo" — os agendamentos/vendas
    // acima reaproveitam $existingPatient de propósito.
    Patient::factory()->for($ctx['organization'])->create();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('indicators.todayAppointmentsCount', 1)
            ->where('indicators.pendingConfirmationsCount', 2)
            ->where('indicators.revenueThisMonthCents', 15000)
            // $existingPatient + o paciente criado logo acima.
            ->where('indicators.newPatientsThisMonthCount', 2));
});

it('shows appointments-by-weekday, revenue-by-month and occupancy-by-professional chart data for an owner', function () {
    $ctx = ownerActingInOrganization();
    $professional = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Dra Juliana Cruz']);

    Appointment::factory()->for($ctx['organization'])->for($professional)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->setTime(9, 0),
    ]);

    $patient = Patient::factory()->for($ctx['organization'])->create();
    Sale::factory()->create([
        'organization_id' => $ctx['organization']->id,
        'patient_id' => $patient->id,
        'status' => SaleStatus::Confirmed,
        'total_cents' => 20000,
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('indicators.charts.appointmentsByWeekday', 7)
            ->has('indicators.charts.revenueByMonth', 6)
            ->has('indicators.charts.occupancyByProfessional', 1)
            ->where('indicators.charts.occupancyByProfessional.0.label', 'Dra Juliana Cruz')
            ->where('indicators.charts.occupancyByProfessional.0.count', 1)
            ->where(
                'indicators.charts.revenueByMonth.5.total_cents',
                20000,
            ));
});

it('groups occupancy strictly by professional_id and labels by the professional\'s display_name, never by the linked user\'s name', function () {
    // Reproduz o cenário real que motivou este teste: a profissional e o
    // usuário vinculado a ela têm nomes diferentes (comum quando o
    // display_name inclui título/tratamento, ex. "Dra."). O card nunca
    // pode usar App\Models\User::name para rotular ou agrupar a barra —
    // só professional_id (agrupamento) e Professional::display_name
    // (rótulo).
    $ctx = ownerActingInOrganization();
    $user = User::factory()->create(['name' => 'Fernanda']);
    $professional = Professional::factory()->for($ctx['organization'])->create([
        'user_id' => $user->id,
        'display_name' => 'Dra Fernanda',
    ]);

    Appointment::factory()->for($ctx['organization'])->for($professional)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->setTime(9, 0),
        'ends_at' => now()->setTime(9, 30),
    ]);
    Appointment::factory()->for($ctx['organization'])->for($professional)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->setTime(10, 0),
        'ends_at' => now()->setTime(10, 30),
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('indicators.charts.occupancyByProfessional', 1)
            ->where('indicators.charts.occupancyByProfessional.0.label', 'Dra Fernanda')
            ->where('indicators.charts.occupancyByProfessional.0.count', 2));
});

it('never breaks the occupancy-by-professional chart when a professional with an appointment this month was soft-deleted', function () {
    $ctx = ownerActingInOrganization();
    $professional = Professional::factory()->for($ctx['organization'])->create(['display_name' => 'Fernanda']);

    Appointment::factory()->for($ctx['organization'])->for($professional)->create([
        'status' => AppointmentStatus::Confirmed,
        'starts_at' => now()->setTime(9, 0),
        'ends_at' => now()->setTime(9, 30),
    ]);

    $professional->delete();

    $this->actingAs($ctx['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('indicators.charts.occupancyByProfessional', 1)
            ->where('indicators.charts.occupancyByProfessional.0.label', 'Fernanda')
            ->where('indicators.charts.occupancyByProfessional.0.count', 1));
});

it('hides appointment/patient-based indicators for a role without that permission, keeping the sales indicator it does have (Finance)', function () {
    $ctx = ownerActingInOrganization();
    seedSystemRoles($ctx['organization']);

    // Finance (ver App\Enums\SystemRole::defaultPermissions) tem
    // sales.view, mas não appointments.view nem patients.view — combinação
    // real que não existe em nenhum outro papel de sistema, útil para
    // provar que cada indicador respeita sua própria Policy de verdade
    // (não é tudo-ou-nada).
    $role = Role::query()->where('organization_id', $ctx['organization']->id)->where('slug', SystemRole::Finance->value)->firstOrFail();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($ctx['organization'])->for($member)->create(['role_id' => $role->id]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($ctx['headquarters'], 'unit')->create();

    $patient = Patient::factory()->for($ctx['organization'])->create();
    Sale::factory()->create([
        'organization_id' => $ctx['organization']->id,
        'patient_id' => $patient->id,
        'status' => SaleStatus::Confirmed,
        'total_cents' => 5000,
    ]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('indicators.todayAppointmentsCount', null)
            ->where('indicators.newPatientsThisMonthCount', null)
            ->where('indicators.revenueThisMonthCents', 5000)
            ->where('indicators.charts.appointmentsByWeekday', null)
            ->where('indicators.charts.occupancyByProfessional', null)
            ->has('indicators.charts.revenueByMonth', 6));
});
