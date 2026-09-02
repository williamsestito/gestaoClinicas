<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class SetSitePublishedStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(?SiteSetting $siteSetting, bool $published, ?Organization $organization = null): SiteSetting
    {
        if (! $siteSetting) {
            throw ValidationException::withMessages([
                'site' => 'Configure o conteúdo do site antes de publicá-lo.',
            ]);
        }

        $siteSetting->update(['is_published' => $published]);

        $this->auditLogger->log(
            $published ? AuditAction::Activated : AuditAction::Deactivated,
            auditable: $siteSetting,
            after: ['is_published' => $published],
            organization: $organization,
        );

        return $siteSetting;
    }
}
