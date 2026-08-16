<?php

declare(strict_types=1);

use App\Enums\WaitlistEntryStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Unit;
use App\Models\WaitlistEntry;

function waitlistEntryForOrganization(Organization $organization): WaitlistEntry
{
    return WaitlistEntry::factory()->create([
        'organization_id' => $organization->id,
        'unit_id' => Unit::factory()->for($organization)->create()->id,
        'service_id' => Service::factory()->for($organization)->create()->id,
        'patient_id' => Patient::factory()->for($organization)->create()->id,
    ]);
}

it('adds a patient to the waitlist for a unit', function () {
    $setup = appointmentSetup();

    $this->actingAs($setup['user'])->post('/settings/appointments/waitlist', [
        'unit_id' => $setup['unit']->id,
        'service_id' => $setup['service']->id,
        'patient_id' => $setup['patient']->id,
    ])->assertRedirect();

    $entry = WaitlistEntry::query()->where('patient_id', $setup['patient']->id)->firstOrFail();
    expect($entry->status)->toBe(WaitlistEntryStatus::Waiting)
        ->and($entry->professional_id)->toBeNull();
});

it('converts a waiting entry into a real appointment, marking it converted and linked', function () {
    $setup = appointmentSetup();
    $entry = WaitlistEntry::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'service_id' => $setup['service']->id,
        'patient_id' => $setup['patient']->id,
    ]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'waitlist_entry_id' => $entry->id,
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->status)->toBe(WaitlistEntryStatus::Converted)
        ->and($entry->appointment_id)->not->toBeNull();
});

it('does not convert the same waitlist entry twice', function () {
    $setup = appointmentSetup();
    $entry = WaitlistEntry::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'service_id' => $setup['service']->id,
        'patient_id' => $setup['patient']->id,
    ]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'waitlist_entry_id' => $entry->id,
    ])->assertRedirect();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T10:00:00',
        'waitlist_entry_id' => $entry->id,
    ])->assertSessionHasErrors('waitlist_entry_id');
});

it('does not convert a waitlist entry belonging to another organization', function () {
    $setup = appointmentSetup();
    $otherOrganization = Organization::factory()->create();
    $entry = waitlistEntryForOrganization($otherOrganization);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'waitlist_entry_id' => $entry->id,
    ])->assertSessionHasErrors('waitlist_entry_id');
});

it('cancels a waiting entry, removing it from the list', function () {
    $user = actingOwnerWithActiveContext();
    $organization = $user->organizationMemberships()->first()->organization;
    $entry = waitlistEntryForOrganization($organization);

    $this->actingAs($user)->patch("/settings/appointments/waitlist/{$entry->id}/cancel")
        ->assertRedirect();

    expect($entry->fresh()->status)->toBe(WaitlistEntryStatus::Cancelled);
});

it('blocks cancelling a waitlist entry belonging to another organization even with a valid id', function () {
    $user = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $entry = waitlistEntryForOrganization($otherOrganization);

    $this->actingAs($user)->patch("/settings/appointments/waitlist/{$entry->id}/cancel")
        ->assertNotFound();
});
