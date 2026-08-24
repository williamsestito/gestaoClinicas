<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\ServiceAvailabilityScope;
use App\Models\Service;
use App\Support\Auditing\AuditLogger;
use App\Support\ServiceLinkSynchronizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateServiceAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, code: string, description: ?string, default_duration_minutes: int, buffer_before_minutes: int, buffer_after_minutes: int, default_price_cents: ?int, cost_cents: ?int, margin_percentage: ?int, max_discount_percentage: ?int, color: ?string, is_public: bool, requires_manual_confirmation: bool, internal_notes: ?string, unit_availability_scope: string, specialty_ids: array<int, string>, unit_ids: array<int, string>}  $attributes
     */
    public function handle(Service $service, array $attributes): Service
    {
        $allowed = [
            'name' => $attributes['name'],
            'code' => $attributes['code'],
            'description' => $attributes['description'],
            'default_duration_minutes' => $attributes['default_duration_minutes'],
            'buffer_before_minutes' => $attributes['buffer_before_minutes'],
            'buffer_after_minutes' => $attributes['buffer_after_minutes'],
            'default_price_cents' => $attributes['default_price_cents'],
            'cost_cents' => $attributes['cost_cents'],
            'margin_percentage' => $attributes['margin_percentage'],
            'max_discount_percentage' => $attributes['max_discount_percentage'],
            'color' => $attributes['color'],
            'is_public' => $attributes['is_public'],
            'requires_manual_confirmation' => $attributes['requires_manual_confirmation'],
            'internal_notes' => $attributes['internal_notes'],
            'unit_availability_scope' => $attributes['unit_availability_scope'],
        ];

        $before = $service->only(array_keys($allowed));

        try {
            DB::transaction(function () use ($service, $allowed, $attributes) {
                $service->update($allowed);

                ServiceLinkSynchronizer::syncSpecialties($service, $attributes['specialty_ids']);
                ServiceLinkSynchronizer::syncUnits(
                    $service,
                    ServiceAvailabilityScope::from($attributes['unit_availability_scope']) === ServiceAvailabilityScope::SelectedUnits
                        ? $attributes['unit_ids']
                        : [],
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Já existe um serviço com este código nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $service,
            before: $before,
            after: $service->only(array_keys($allowed)),
            organization: $service->organization,
        );

        return $service;
    }
}
