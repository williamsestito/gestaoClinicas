<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\Storage;

class DestroyProfessionalPhotoAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional): Professional
    {
        if ($professional->photo_path && Storage::disk('public')->exists($professional->photo_path)) {
            Storage::disk('public')->delete($professional->photo_path);
        }

        $professional->update(['photo_path' => null]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $professional,
            after: ['photo_path' => 'removed'],
            organization: $professional->organization,
        );

        return $professional;
    }
}
