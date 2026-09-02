<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\MedicalRecordStatus;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAddendum;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Única forma de corrigir um prontuário já finalizado (RN-007) — o adendo
 * é sempre criado, nunca atualiza ou apaga o conteúdo original. Não existe
 * Action de update/destroy para `MedicalRecordAddendum`.
 */
class AddMedicalRecordAddendumAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(MedicalRecord $medicalRecord, Professional $author, string $body): MedicalRecordAddendum
    {
        if ($medicalRecord->status !== MedicalRecordStatus::Finalized) {
            throw ValidationException::withMessages([
                'status' => 'Só é possível adicionar um adendo a um prontuário já finalizado.',
            ]);
        }

        $addendum = $medicalRecord->addenda()->create([
            'organization_id' => $medicalRecord->organization_id,
            'unit_id' => $medicalRecord->unit_id,
            'professional_id' => $author->id,
            'body' => $body,
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $addendum,
            after: ['body' => $addendum->body],
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return $addendum;
    }
}
