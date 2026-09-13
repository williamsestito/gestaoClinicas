<?php

declare(strict_types=1);

use App\Models\Appointment;

it('lists the day\'s appointments for the admin agenda', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    $appointment = Appointment::factory()->create([
        'organization_id' => $professional->organization_id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
        'ends_at' => '2026-08-03T09:30:00',
    ]);

    $this->actingAs($user)
        ->get('/settings/appointments?date=2026-08-03')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $appointment->id)
            ->where('appointments.0.professional_name', $professional->display_name)
        );
});

it('never breaks the admin agenda when the appointment\'s professional was soft-deleted after the appointment was created', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    $appointment = Appointment::factory()->create([
        'organization_id' => $professional->organization_id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
        'ends_at' => '2026-08-03T09:30:00',
    ]);

    $professionalDisplayName = $professional->display_name;
    $professional->delete();

    $this->actingAs($user)
        ->get('/settings/appointments?date=2026-08-03')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $appointment->id)
            // Histórico continua mostrando com qual profissional foi, mesmo
            // após o desligamento (soft delete) — nunca quebra a página nem
            // apaga a informação (regra de exclusão lógica do projeto).
            ->where('appointments.0.professional_name', $professionalDisplayName)
        );
});

it('never breaks the "propose alternate time" screen when the appointment\'s professional was soft-deleted', function () {
    ['user' => $user, 'unit' => $unit, 'professional' => $professional, 'service' => $service, 'patient' => $patient] = appointmentSetup();

    $appointment = Appointment::factory()->create([
        'organization_id' => $professional->organization_id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'patient_id' => $patient->id,
        'service_id' => $service->id,
        'starts_at' => '2026-08-03T09:00:00',
        'ends_at' => '2026-08-03T09:30:00',
    ]);

    $professionalDisplayName = $professional->display_name;
    $professional->delete();

    $this->actingAs($user)
        ->get("/settings/appointments/{$appointment->id}/propose")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appointment.professional_name', $professionalDisplayName)
        );
});
