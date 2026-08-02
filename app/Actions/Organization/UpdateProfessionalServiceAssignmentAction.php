<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use App\Models\ProfessionalService;
use App\Support\Auditing\AuditLogger;
use App\Support\ProfessionalServiceUnitSynchronizer;
use Illuminate\Support\Facades\DB;

class UpdateProfessionalServiceAssignmentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ProfessionalService $link, array $attributes): ProfessionalService
    {
        $allowed = [
            'custom_duration_minutes' => $attributes['custom_duration_minutes'],
            'custom_price_cents' => $attributes['custom_price_cents'],
            'custom_buffer_before_minutes' => $attributes['custom_buffer_before_minutes'],
            'custom_buffer_after_minutes' => $attributes['custom_buffer_after_minutes'],
            'unit_scope' => $attributes['unit_scope'],
        ];

        $before = $link->only(array_keys($allowed));

        DB::transaction(function () use ($link, $allowed, $attributes) {
            $link->update($allowed);

            ProfessionalServiceUnitSynchronizer::sync(
                $link,
                ProfessionalServiceUnitScope::from($attributes['unit_scope']) === ProfessionalServiceUnitScope::SelectedUnits
                    ? $attributes['unit_ids']
                    : [],
            );

            if ($link->status === RecordStatus::Active && $link->hasNoCompatibleUnits()) {
                $link->update(['status' => RecordStatus::Inactive]);
            }
        });

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $link,
            before: $before,
            after: $link->only(array_keys($allowed)),
            organization: $link->organization,
        );

        return $link->fresh();
    }
}
