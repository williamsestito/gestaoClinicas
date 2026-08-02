<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\ProfessionalSpecialty;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Troca a especialidade principal de um profissional dentro de uma
 * transação: remove o indicador da anterior (se houver) e define a nova.
 * O índice único parcial `professional_specialty_one_primary_per_professional`
 * é a defesa final contra corrida entre requisições concorrentes.
 */
class SetPrimaryProfessionalSpecialtyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProfessionalSpecialty $link): ProfessionalSpecialty
    {
        try {
            DB::transaction(function () use ($link) {
                $link->professional->specialtyLinks()
                    ->where('is_primary', true)
                    ->where('id', '!=', $link->id)
                    ->update(['is_primary' => false]);

                $link->update(['is_primary' => true]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'specialty' => 'Não foi possível definir a especialidade principal — tente novamente.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::PrimaryProfessionalSpecialtyChanged,
            auditable: $link->professional,
            after: ['specialty_id' => $link->specialty_id],
            organization: $link->organization,
        );

        return $link->fresh();
    }
}
