<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Support\Carbon;

it('creates a weekly recurring series sharing a common recurrence_group_id', function () {
    $setup = appointmentSetup();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'recurrence_weeks' => 4,
    ])->assertRedirect();

    $created = Appointment::query()->where('patient_id', $setup['patient']->id)->orderBy('starts_at')->get();
    expect($created)->toHaveCount(4)
        ->and($created->pluck('recurrence_group_id')->unique())->toHaveCount(1)
        ->and($created->first()->recurrence_group_id)->not->toBeNull();

    foreach ($created as $index => $appointment) {
        expect(abs($appointment->starts_at->diffInWeeks(
            $created->first()->starts_at,
        )))->toBe((float) $index);
    }
});

it('skips a conflicting occurrence without aborting the rest of the series', function () {
    $setup = appointmentSetup();
    $otherPatient = Patient::factory()->for($setup['organization'])->create();

    // Bloqueia de antemão a 3ª ocorrência (2026-08-17, terceira segunda) —
    // mesma construção de horário (fuso da unidade -> UTC) usada pelo
    // controller/Action reais, ver createConfirmedAppointment() em
    // AppointmentLifecycleTest.php.
    $thirdOccurrenceStartsAt = Carbon::parse('2026-08-03 09:00', 'America/Sao_Paulo')->addWeeks(2)->utc();
    Appointment::factory()->create([
        'organization_id' => $setup['organization']->id,
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $otherPatient->id,
        'service_id' => $setup['service']->id,
        'starts_at' => $thirdOccurrenceStartsAt,
        'ends_at' => $thirdOccurrenceStartsAt->copy()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed,
    ]);

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'recurrence_weeks' => 4,
    ])->assertRedirect();

    $created = Appointment::query()->where('patient_id', $setup['patient']->id)->get();
    expect($created)->toHaveCount(3);
});

it('rejects a recurrence outside the 2-52 week range', function () {
    $setup = appointmentSetup();

    $this->actingAs($setup['user'])->post('/settings/appointments', [
        'unit_id' => $setup['unit']->id,
        'professional_id' => $setup['professional']->id,
        'patient_id' => $setup['patient']->id,
        'service_id' => $setup['service']->id,
        'starts_at' => appointmentMonday()->toDateString().'T09:00:00',
        'recurrence_weeks' => 1,
    ])->assertSessionHasErrors('recurrence_weeks');
});
