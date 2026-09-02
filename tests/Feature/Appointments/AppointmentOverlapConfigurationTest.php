<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Models\SharedResource;

it('still blocks overlapping appointments for a professional when the organization has not enabled overlap', function () {
    $setup = appointmentSetup();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
    ])->assertRedirect();

    $otherPatient = Patient::factory()->for($setup['organization'])->create();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
    ])->assertSessionHasErrors('starts_at');
});

it('allows a real overlap when the organization enables "encaixe", and audits the conflict', function () {
    $setup = appointmentSetup();
    $setup['organization']->update(['allow_appointment_overlap' => true]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
    ])->assertRedirect();

    $otherPatient = Patient::factory()->for($setup['organization'])->create();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
    ])->assertRedirect();

    $overlapping = Appointment::query()->where('professional_id', $setup['professional']->id)->get();
    expect($overlapping)->toHaveCount(2)
        ->and(AuditLog::query()->where('action', AuditAction::ConflictDetected)->exists())->toBeTrue();
});

it('never allows a resource conflict to be bypassed by the organization overlap toggle', function () {
    $setup = appointmentSetup();
    $setup['organization']->update(['allow_appointment_overlap' => true]);
    $resource = SharedResource::factory()->for($setup['organization'])->for($setup['unit'])->create();

    $otherProfessional = Professional::factory()->for($setup['organization'])->create(['status' => RecordStatus::Active]);
    $otherProfessionalUnit = ProfessionalUnit::factory()->for($otherProfessional)->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'status' => RecordStatus::Active,
    ]);
    ProfessionalWorkingHour::factory()->for($otherProfessionalUnit, 'professionalUnit')->create([
        'organization_id' => $setup['organization']->id,
        'weekday' => Weekday::Monday,
        'starts_at' => '08:00',
        'ends_at' => '18:00',
    ]);
    ProfessionalService::factory()->for($otherProfessional)->create([
        'organization_id' => $setup['organization']->id,
        'service_id' => $setup['service']->id,
    ]);
    $otherPatient = Patient::factory()->for($setup['organization'])->create();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'resource_ids' => [$resource->id],
    ])->assertRedirect();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $otherProfessional->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'resource_ids' => [$resource->id],
    ])->assertSessionHasErrors('resource_ids');
});
