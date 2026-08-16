<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Patient;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\Storage;

/**
 * Disco `local` (privado) — ver comentário em UpdatePatientPhotoAction.
 */
class DestroyPatientPhotoAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Patient $patient): Patient
    {
        if ($patient->photo_path && Storage::disk('local')->exists($patient->photo_path)) {
            Storage::disk('local')->delete($patient->photo_path);
        }

        $patient->update(['photo_path' => null]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $patient,
            after: ['photo_path' => 'removed'],
            organization: $patient->organization,
        );

        return $patient;
    }
}
