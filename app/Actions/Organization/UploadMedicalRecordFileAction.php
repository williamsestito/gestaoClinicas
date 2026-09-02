<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\MedicalRecordFileCategory;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordFile;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\UploadedFile;

/**
 * Armazenamento privado (Seção 11 do documento de visão) — usa o disco
 * padrão da aplicação (`config('filesystems.default')`, `local` neste
 * ambiente), nunca um disco público. Cada arquivo fica isolado por
 * prontuário (`medical-record-files/{medical_record_id}/...`).
 */
class UploadMedicalRecordFileAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(MedicalRecord $medicalRecord, UploadedFile $file, MedicalRecordFileCategory $category, User $uploadedBy): MedicalRecordFile
    {
        $disk = config('filesystems.default');
        $path = $file->store("medical-record-files/{$medicalRecord->id}", $disk);

        $medicalRecordFile = $medicalRecord->files()->create([
            'organization_id' => $medicalRecord->organization_id,
            'unit_id' => $medicalRecord->unit_id,
            'uploaded_by' => $uploadedBy->id,
            'category' => $category,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $medicalRecordFile,
            after: [
                'category' => $category->value,
                'original_filename' => $medicalRecordFile->original_filename,
            ],
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return $medicalRecordFile;
    }
}
