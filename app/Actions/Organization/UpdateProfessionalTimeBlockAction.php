<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ProfessionalTimeBlockScope;
use App\Models\ProfessionalTimeBlock;
use App\Support\Auditing\AuditLogger;
use App\Support\Availability\TimeBlockOverlapGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProfessionalTimeBlockAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ProfessionalTimeBlock $timeBlock, array $attributes): ProfessionalTimeBlock
    {
        $scope = ProfessionalTimeBlockScope::from($attributes['scope']);
        $professional = $timeBlock->professional;

        if ($attributes['ends_at']->lte($attributes['starts_at'])) {
            throw ValidationException::withMessages([
                'ends_date' => 'O fim do período deve ser posterior ao início.',
            ]);
        }

        $before = $timeBlock->only(['type', 'scope', 'unit_id', 'starts_at', 'ends_at', 'is_all_day']);

        try {
            DB::transaction(function () use ($timeBlock, $professional, $scope, $attributes) {
                TimeBlockOverlapGuard::assertNoConflict(
                    $professional,
                    $scope,
                    $attributes['unit_id'],
                    $attributes['starts_at'],
                    $attributes['ends_at'],
                    excludingId: $timeBlock->id,
                );

                $timeBlock->update([
                    'unit_id' => $attributes['unit_id'],
                    'type' => $attributes['type'],
                    'scope' => $scope,
                    'starts_at' => $attributes['starts_at'],
                    'ends_at' => $attributes['ends_at'],
                    'is_all_day' => $attributes['is_all_day'],
                    'reason' => $attributes['reason'],
                    'internal_notes' => $attributes['internal_notes'],
                ]);
            });
        } catch (ValidationException $exception) {
            $this->auditLogger->log(
                AuditAction::ConflictDetected,
                auditable: $timeBlock,
                after: ['type' => $attributes['type'], 'scope' => $scope->value],
                organization: $professional->organization,
            );

            throw $exception;
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $timeBlock,
            before: $before,
            after: $timeBlock->only(['type', 'scope', 'unit_id', 'starts_at', 'ends_at', 'is_all_day']),
            organization: $professional->organization,
        );

        return $timeBlock;
    }
}
