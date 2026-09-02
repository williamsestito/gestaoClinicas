<?php

declare(strict_types=1);

namespace App\Support\Patients;

use App\Models\Patient;
use Illuminate\Support\Carbon;

/**
 * RN-004: paciente menor de 18 anos precisa de ao menos um responsável
 * legal ativo. Reaproveitado na criação (contra o array de responsáveis
 * recém-enviado no mesmo formulário, antes de existir um Patient
 * persistido) e na atualização/remoção de responsável (contra os registros
 * já persistidos em App\Models\PatientResponsible).
 */
final class MinorGuardianGuard
{
    public static function isMinor(string $birthDate): bool
    {
        return Carbon::parse($birthDate)->age < 18;
    }

    /**
     * @param  array<array-key, mixed>  $responsibles
     */
    public static function hasLegalGuardianInPayload(array $responsibles): bool
    {
        foreach ($responsibles as $responsible) {
            if (is_array($responsible) && filter_var($responsible['is_legal_guardian'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Usado ao remover/desativar o papel de responsável legal de um
     * vínculo já persistido: garante que sempre sobra outro responsável
     * legal ativo para um paciente menor, mesmo padrão de
     * RemoveProfessionalSpecialtyAction::hasOtherActiveLinks().
     */
    public static function hasOtherActiveLegalGuardian(Patient $patient, string $excludingResponsibleId): bool
    {
        return $patient->responsibles()
            ->where('id', '!=', $excludingResponsibleId)
            ->where('is_legal_guardian', true)
            ->exists();
    }
}
