<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalService;
use App\Support\Auditing\AuditLogger;
use App\Support\ProfessionalServiceUnitSynchronizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignServiceToProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Professional $professional, array $attributes): ProfessionalService
    {
        try {
            $link = DB::transaction(function () use ($professional, $attributes) {
                $link = $professional->serviceLinks()->create([
                    'organization_id' => $professional->organization_id,
                    'service_id' => $attributes['service_id'],
                    'custom_duration_minutes' => $attributes['custom_duration_minutes'],
                    'custom_price_cents' => $attributes['custom_price_cents'],
                    'custom_buffer_before_minutes' => $attributes['custom_buffer_before_minutes'],
                    'custom_buffer_after_minutes' => $attributes['custom_buffer_after_minutes'],
                    'unit_scope' => $attributes['unit_scope'],
                    'status' => RecordStatus::Active,
                ]);

                if (ProfessionalServiceUnitScope::from($attributes['unit_scope']) === ProfessionalServiceUnitScope::SelectedUnits) {
                    ProfessionalServiceUnitSynchronizer::sync($link, $attributes['unit_ids']);
                }

                if ($link->hasNoCompatibleUnits()) {
                    $link->update(['status' => RecordStatus::Inactive]);
                }

                return $link;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'service_id' => 'Já existe um vínculo ativo com este serviço.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $link,
            after: ['professional_id' => $professional->id, 'service_id' => $attributes['service_id'], 'status' => $link->status->value],
            organization: $professional->organization,
        );

        return $link->fresh();
    }
}
