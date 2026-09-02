<?php

declare(strict_types=1);

use App\Enums\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAddendum;

it('lets the author edit the draft and keeps it in draft status', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}", [
            'anamnesis' => 'Paciente relata dor lombar há 3 dias.',
            'evaluation' => 'Tensão muscular leve.',
            'has_return_right' => true,
            'return_window_days' => 15,
        ])
        ->assertRedirect();

    $medicalRecord->refresh();
    expect($medicalRecord->status)->toBe(MedicalRecordStatus::Draft)
        ->and($medicalRecord->anamnesis)->toBe('Paciente relata dor lombar há 3 dias.')
        ->and($medicalRecord->has_return_right)->toBeTrue()
        ->and($medicalRecord->return_window_days)->toBe(15);
});

it('finalizes a draft, stamping finalized_at', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}/finalizar")
        ->assertRedirect();

    $medicalRecord->refresh();
    expect($medicalRecord->status)->toBe(MedicalRecordStatus::Finalized)
        ->and($medicalRecord->finalized_at)->not->toBeNull();
});

it('blocks updating the draft fields directly once the record is finalized (RN-007)', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->finalized()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}", ['anamnesis' => 'Tentativa de reescrever o original.'])
        ->assertForbidden();

    expect($medicalRecord->fresh()->anamnesis)->not->toBe('Tentativa de reescrever o original.');
});

it('blocks finalizing an already-finalized record', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->finalized()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}/finalizar")
        ->assertForbidden();
});

it('blocks adding an addendum to a record that is still a draft', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->post("/settings/prontuarios/{$medicalRecord->id}/adendos", ['body' => 'Correção qualquer.'])
        ->assertForbidden();
});

it('adds an addendum to a finalized record, preserving the original content and the addendum author', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->finalized()->create([
        'appointment_id' => $setup['appointment']->id,
        'anamnesis' => 'Conteúdo original.',
    ]);

    $this->actingAs($setup['professionalUser'])
        ->post("/settings/prontuarios/{$medicalRecord->id}/adendos", ['body' => 'Correção: paciente também relatou febre.'])
        ->assertRedirect();

    $medicalRecord->refresh();
    expect($medicalRecord->anamnesis)->toBe('Conteúdo original.')
        ->and($medicalRecord->addenda)->toHaveCount(1);

    $addendum = MedicalRecordAddendum::query()->where('medical_record_id', $medicalRecord->id)->firstOrFail();
    expect($addendum->body)->toBe('Correção: paciente também relatou febre.')
        ->and($addendum->professional_id)->toBe($setup['professional']->id);
});

it('never lets an addendum be updated or deleted — no such routes exist', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->finalized()->create(['appointment_id' => $setup['appointment']->id]);
    $addendum = MedicalRecordAddendum::factory()->create(['medical_record_id' => $medicalRecord->id]);

    $this->actingAs($setup['professionalUser'])->patch("/settings/prontuarios/adendos/{$addendum->id}")->assertNotFound();
    $this->actingAs($setup['professionalUser'])->delete("/settings/prontuarios/adendos/{$addendum->id}")->assertNotFound();
});

it('releases a finalized record to the patient portal, stamping released_to_patient_at', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->finalized()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}/liberar")
        ->assertRedirect();

    expect($medicalRecord->fresh()->released_to_patient_at)->not->toBeNull();
});

it('blocks releasing a record that is still a draft', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $this->actingAs($setup['professionalUser'])
        ->patch("/settings/prontuarios/{$medicalRecord->id}/liberar")
        ->assertForbidden();
});
