<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
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

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_unverified_users_are_redirected_to_the_verification_notice()
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }
}
