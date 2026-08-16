<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\SafeFileReplacer;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Mesmo fluxo seguro de App\Actions\Organization\UpdateProfessionalPhotoAction
 * (App\Support\Site\SafeFileReplacer): o arquivo antigo só é removido depois
 * que a nova referência foi persistida com sucesso.
 *
 * Disco `local` (privado), não `public`: diferente da foto do profissional
 * (propositalmente pública, com opt-in explícito via `is_public`/vitrine do
 * site), a foto do paciente é dado privado sem nenhuma finalidade pública —
 * nunca deve ficar acessível por um link direto sem autenticação. Servida
 * por PatientController::showPhoto(), atrás da mesma Policy de visualização
 * do paciente.
 */
class UpdatePatientPhotoAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Patient $patient, UploadedFile $photo): Patient
    {
        $replacer = new SafeFileReplacer('local');
        $replacer->stage($patient, 'photo_path', $photo, 'patient-photos');

        try {
            $patient->save();
        } catch (Throwable $e) {
            $replacer->rollback();

            throw $e;
        }

        $replacer->commit();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $patient,
            after: ['photo_path' => 'updated'],
            organization: $patient->organization,
        );

        return $patient;
    }
}
