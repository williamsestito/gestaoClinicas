<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\MedicalRecordFileCategory;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordFile;
use App\Models\Professional;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('uploads a valid PDF file to a medical record and audits the upload', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $pdf = UploadedFile::fake()->createWithContent('exame.pdf', "%PDF-1.4\n%EOF");

    $this->actingAs($setup['professionalUser'])
        ->post("/settings/prontuarios/{$medicalRecord->id}/arquivos", [
            'category' => MedicalRecordFileCategory::Exam->value,
            'file' => $pdf,
        ])
        ->assertRedirect();

    $file = MedicalRecordFile::query()->where('medical_record_id', $medicalRecord->id)->firstOrFail();
    expect($file->category)->toBe(MedicalRecordFileCategory::Exam)
        ->and($file->original_filename)->toBe('exame.pdf');

    Storage::disk('local')->assertExists($file->path);
});

it('uploads a valid image file to a medical record', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $image = UploadedFile::fake()->image('foto.jpg');

    $this->actingAs($setup['professionalUser'])
        ->post("/settings/prontuarios/{$medicalRecord->id}/arquivos", [
            'category' => MedicalRecordFileCategory::ClinicalPhoto->value,
            'file' => $image,
        ])
        ->assertRedirect();

    expect(MedicalRecordFile::query()->where('medical_record_id', $medicalRecord->id)->count())->toBe(1);
});

it('rejects a PDF upload whose content does not really match a PDF signature', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $fakePdf = UploadedFile::fake()->createWithContent('exame.pdf', 'isto nao e um pdf de verdade');

    $this->actingAs($setup['professionalUser'])
        ->post("/settings/prontuarios/{$medicalRecord->id}/arquivos", [
            'category' => MedicalRecordFileCategory::Exam->value,
            'file' => $fakePdf,
        ])
        ->assertSessionHasErrors('file');

    expect(MedicalRecordFile::query()->where('medical_record_id', $medicalRecord->id)->count())->toBe(0);
});

it('rejects an upload with a disallowed extension', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);

    $script = UploadedFile::fake()->createWithContent('malicioso.php', '<?php echo "oi"; ?>');

    $this->actingAs($setup['professionalUser'])
        ->post("/settings/prontuarios/{$medicalRecord->id}/arquivos", [
            'category' => MedicalRecordFileCategory::Exam->value,
            'file' => $script,
        ])
        ->assertSessionHasErrors('file');
});

it('audits viewing and downloading a medical record file (RN-008)', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);
    Storage::disk('local')->put('medical-record-files/test.pdf', "%PDF-1.4\n%EOF");
    $file = MedicalRecordFile::factory()->create([
        'medical_record_id' => $medicalRecord->id,
        'disk' => 'local',
        'path' => 'medical-record-files/test.pdf',
    ]);

    $this->actingAs($setup['professionalUser'])
        ->get("/settings/prontuarios/{$medicalRecord->id}/arquivos/{$file->id}")
        ->assertOk();

    $this->actingAs($setup['professionalUser'])
        ->get("/settings/prontuarios/{$medicalRecord->id}/arquivos/{$file->id}/download")
        ->assertOk();

    expect(AuditLog::query()->where('auditable_id', $file->id)->where('action', AuditAction::Viewed)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_id', $file->id)->where('action', AuditAction::Downloaded)->exists())->toBeTrue();
});

it('blocks a colleague professional from viewing or downloading another professional\'s clinical file', function () {
    $setup = medicalRecordSetup();
    $medicalRecord = MedicalRecord::factory()->create(['appointment_id' => $setup['appointment']->id]);
    Storage::disk('local')->put('medical-record-files/test.pdf', "%PDF-1.4\n%EOF");
    $file = MedicalRecordFile::factory()->create([
        'medical_record_id' => $medicalRecord->id,
        'disk' => 'local',
        'path' => 'medical-record-files/test.pdf',
    ]);

    $colleagueUser = medicalRecordStaffUser($setup['organization'], SystemRole::Professional);
    Professional::factory()->for($setup['organization'])->create(['user_id' => $colleagueUser->id, 'status' => RecordStatus::Active]);
    session(['active_organization_id' => $setup['organization']->id]);

    $this->actingAs($colleagueUser)
        ->get("/settings/prontuarios/{$medicalRecord->id}/arquivos/{$file->id}")
        ->assertForbidden();
});
