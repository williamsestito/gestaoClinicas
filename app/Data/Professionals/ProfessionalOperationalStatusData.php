<?php

declare(strict_types=1);

namespace App\Data\Professionals;

use App\Enums\ProfessionalOperationalStatus;

/**
 * Resultado do cálculo de situação operacional de um profissional — nunca
 * persistido (ver App\Services\Professionals\ProfessionalOperationalStatusResolver).
 * `reasons` são motivos que impedem a operação (configuração incompleta ou
 * profissional inativo); `warnings` são alertas que não impedem a operação
 * mas merecem atenção (ex.: registro vencido, ausência em andamento).
 */
final readonly class ProfessionalOperationalStatusData
{
    /**
     * @param  array<int, string>  $reasons
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public bool $isOperational,
        public ProfessionalOperationalStatus $status,
        public array $reasons,
        public array $warnings,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'is_operational' => $this->isOperational,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reasons' => $this->reasons,
            'warnings' => $this->warnings,
        ];
    }
}
