<?php

declare(strict_types=1);

use App\Actions\Organization\SeedSystemRolesAction;
use App\Enums\AppointmentRequestStatus;
use App\Enums\AppointmentStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Support\Carbon;

/** @return array{0: User, 1: Organization, 2: Professional} */
function professionalDashboardSetup(): array
{
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();

    $user = User::factory()->create();
    $professional = Professional::factory()->for($organization)->create(['user_id' => $user->id, 'status' => RecordStatus::Active]);
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    return [$user, $organization, $professional];
}

it('shows the professional dashboard data for a user with the Professional role', function () {
    [$user] = professionalDashboardSetup();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('professionalDashboard.period', 'day')
        ->has('professionalDashboard.counters')
        ->has('professionalDashboard.agenda')
        ->has('professionalDashboard.reminders'));
});

it('never shows the professional dashboard to the owner, even with a linked professional record', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $owner = User::factory()->create();
    Professional::factory()->for($organization)->create(['user_id' => $owner->id, 'status' => RecordStatus::Active]);
    $membership = OrganizationMembership::factory()->owner()->for($organization)->for($owner)->create([
        'status' => OrganizationMembershipStatus::Active,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $response = $this->actingAs($owner)->get('/dashboard');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('professionalDashboard', null));
});

it('buckets appointments into open, scheduled and completed counters', function () {
    [$user, $organization, $professional] = professionalDashboardSetup();
    $today = now()->startOfDay();

    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Requested, 'starts_at' => $today->copy()->addHours(9), 'ends_at' => $today->copy()->addHours(9)->addMinutes(30)]);
    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::AwaitingConfirmation, 'starts_at' => $today->copy()->addHours(10), 'ends_at' => $today->copy()->addHours(10)->addMinutes(30)]);
    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => $today->copy()->addHours(11), 'ends_at' => $today->copy()->addHours(11)->addMinutes(30)]);
    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Completed, 'starts_at' => $today->copy()->addHours(8), 'ends_at' => $today->copy()->addHours(8)->addMinutes(30)]);
    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Cancelled, 'starts_at' => $today->copy()->addHours(12), 'ends_at' => $today->copy()->addHours(12)->addMinutes(30)]);

    $response = $this->actingAs($user)->get('/dashboard?date='.$today->toDateString());

    $response->assertInertia(fn ($page) => $page
        ->where('professionalDashboard.counters.open', 2)
        ->where('professionalDashboard.counters.scheduled', 1)
        ->where('professionalDashboard.counters.completed', 1)
        ->where('professionalDashboard.agenda', fn ($agenda) => count($agenda) === 5));
});

it('excludes appointments outside the selected day', function () {
    [$user, $organization, $professional] = professionalDashboardSetup();
    $today = now()->startOfDay();

    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => $today->copy()->addHours(9), 'ends_at' => $today->copy()->addHours(9)->addMinutes(30)]);
    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => $today->copy()->addDays(3)->addHours(9), 'ends_at' => $today->copy()->addDays(3)->addHours(9)->addMinutes(30)]);

    $response = $this->actingAs($user)->get('/dashboard?date='.$today->toDateString());

    $response->assertInertia(fn ($page) => $page->where('professionalDashboard.counters.scheduled', 1));
});

it('expands the range to the whole week or month when requested', function () {
    [$user, $organization, $professional] = professionalDashboardSetup();
    $today = now()->startOfWeek(Carbon::MONDAY)->addDays(2);

    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => $today->copy()->addHours(9), 'ends_at' => $today->copy()->addHours(9)->addMinutes(30)]);
    Appointment::factory()->for($organization)->for($professional)->create(['status' => AppointmentStatus::Confirmed, 'starts_at' => $today->copy()->addDays(1)->addHours(9), 'ends_at' => $today->copy()->addDays(1)->addHours(9)->addMinutes(30)]);

    $weekResponse = $this->actingAs($user)->get('/dashboard?period=week&date='.$today->toDateString());
    $weekResponse->assertInertia(fn ($page) => $page
        ->where('professionalDashboard.period', 'week')
        ->where('professionalDashboard.counters.scheduled', 2));

    $dayResponse = $this->actingAs($user)->get('/dashboard?period=day&date='.$today->toDateString());
    $dayResponse->assertInertia(fn ($page) => $page->where('professionalDashboard.counters.scheduled', 1));
});

it('counts and lists only the professional\'s own pending appointment requests', function () {
    [$user, $organization, $professional] = professionalDashboardSetup();
    $colleague = Professional::factory()->for($organization)->create(['status' => RecordStatus::Active]);

    AppointmentRequest::factory()->for($organization)->for($professional)->create(['status' => AppointmentRequestStatus::Pending, 'name' => 'Lead Meu']);
    AppointmentRequest::factory()->for($organization)->for($professional)->create(['status' => AppointmentRequestStatus::Contacted]);
    AppointmentRequest::factory()->for($organization)->for($colleague)->create(['status' => AppointmentRequestStatus::Pending, 'name' => 'Lead do Colega']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('professionalDashboard.pendingAppointmentRequestsCount', 1)
        ->where('professionalDashboard.pendingAppointmentRequests.0.name', 'Lead Meu'));
});

it('shows an empty state for a Professional-role user without a linked professional record', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    $headquarters = Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    app(SeedSystemRolesAction::class)->handle($organization);
    $role = Role::query()->where('organization_id', $organization->id)->where('slug', SystemRole::Professional->value)->firstOrFail();
    $user = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($user)->create([
        'status' => OrganizationMembershipStatus::Active,
        'role_id' => $role->id,
    ]);
    UnitMembership::factory()->for($membership, 'organizationMembership')->for($headquarters, 'unit')->create();
    session(['active_organization_id' => $organization->id, 'active_unit_id' => $headquarters->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()->assertInertia(fn ($page) => $page->where('professionalDashboard', null));
});
