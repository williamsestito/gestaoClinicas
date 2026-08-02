<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ProfessionalTimeBlockScope;
use App\Enums\RecordStatus;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\TimeBlockOverlapGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProfessionalTimeBlockAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Professional $professional, array $attributes): ProfessionalTimeBlock
    {
        $scope = ProfessionalTimeBlockScope::from($attributes['scope']);

        if ($attributes['ends_at']->lte($attributes['starts_at'])) {
            throw ValidationException::withMessages([
                'ends_date' => 'O fim do período deve ser posterior ao início.',
            ]);
        }

        try {
            $timeBlock = DB::transaction(function () use ($professional, $scope, $attributes) {
                TimeBlockOverlapGuard::assertNoConflict(
                    $professional,
                    $scope,
                    $attributes['unit_id'],
                    $attributes['starts_at'],
                    $attributes['ends_at'],
                );

                return $professional->timeBlocks()->create([
                    'organization_id' => $professional->organization_id,
                    'unit_id' => $attributes['unit_id'],
                    'type' => $attributes['type'],
                    'scope' => $scope,
                    'starts_at' => $attributes['starts_at'],
                    'ends_at' => $attributes['ends_at'],
                    'is_all_day' => $attributes['is_all_day'],
                    'reason' => $attributes['reason'],
                    'internal_notes' => $attributes['internal_notes'],
                    'status' => RecordStatus::Active,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'starts_date' => 'Este período já está cadastrado.',
            ]);
        } catch (ValidationException $exception) {
            $this->auditLogger->log(
                AuditAction::ConflictDetected,
                auditable: $professional,
                after: ['type' => $attributes['type'], 'scope' => $scope->value],
                organization: $professional->organization,
            );

            throw $exception;
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $timeBlock,
            after: $timeBlock->only(['type', 'scope', 'unit_id', 'starts_at', 'ends_at', 'is_all_day', 'status']),
            organization: $professional->organization,
        );

        return $timeBlock;
    }
}
