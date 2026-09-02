<?php

declare(strict_types=1);

namespace App\Services\Professionals;

use App\Data\Professionals\ProfessionalOperationalStatusData;
use App\Enums\ProfessionalOperationalStatus;
use App\Enums\ProfessionalRegistrationValidityStatus;
use App\Enums\RecordStatus;
use App\Models\Professional;
use Illuminate\Support\Carbon;

/**
 * Resolução centralizada da situação operacional de um profissional —
 * único ponto que decide se ele está "pronto para operar", para não
 * espalhar essa regra pela interface. Nunca persiste o resultado.
 */
final class ProfessionalOperationalStatusResolver
{
    public function resolve(Professional $professional): ProfessionalOperationalStatusData
    {
        if ($professional->status !== RecordStatus::Active || $professional->trashed()) {
            return new ProfessionalOperationalStatusData(
                isOperational: false,
                status: ProfessionalOperationalStatus::Inactive,
                reasons: ['Profissional inativo.'],
                warnings: [],
            );
        }

        $reasons = [];
        $warnings = [];

        $hasActiveUnit = $professional->unitLinks()->where('status', RecordStatus::Active)->exists();
        $hasActiveService = $professional->serviceLinks()->where('status', RecordStatus::Active)->exists();
        $hasActiveWorkingHours = $professional->workingHours()->where('professional_working_hours.status', RecordStatus::Active)->exists();

        if (! $hasActiveUnit) {
            $reasons[] = 'Este profissional ainda não possui unidade de atuação ativa.';
        }

        if (! $hasActiveWorkingHours) {
            $reasons[] = 'Este profissional ainda não possui jornada configurada.';
        }

        if (! $hasActiveService) {
            $warnings[] = 'Este profissional ainda não possui serviço ativo.';
        }

        $hasFutureUnitLink = $professional->unitLinks()
            ->where('status', RecordStatus::Active)
            ->where('starts_on', '>', Carbon::today())
            ->exists();

        if ($hasFutureUnitLink) {
            $warnings[] = 'Há um vínculo de unidade programado para o futuro.';
        }

        $primaryRegistration = $professional->primaryRegistration;

        if ($primaryRegistration !== null && $primaryRegistration->validityStatus() === ProfessionalRegistrationValidityStatus::Expired) {
            $warnings[] = 'O registro profissional principal está vencido.';
        }

        // Campos opcionais na criação (ver CreateProfessionalRequest — só
        // nome, e-mail, CPF e senha são obrigatórios) mas esperados num
        // cadastro completo. Nunca bloqueiam a operação — só um lembrete
        // que persiste até o próprio profissional (ou um administrador)
        // completar o cadastro.
        if ($professional->birth_date === null) {
            $warnings[] = 'Data de nascimento não informada.';
        }

        if (blank($professional->bio)) {
            $warnings[] = 'Biografia não preenchida.';
        }

        if (! $professional->specialtyLinks()->where('status', RecordStatus::Active)->exists()) {
            $warnings[] = 'Nenhuma especialidade cadastrada.';
        }

        // Nunca `$primaryRegistration === null` aqui: um profissional pode
        // ter um registro cadastrado e ativo que ainda não foi marcado como
        // principal (`is_primary`) — isso não é "nenhum registro", é só
        // "nenhum definido como principal ainda", uma situação diferente
        // que não merece o mesmo aviso.
        if (! $professional->registrations()->where('status', RecordStatus::Active)->exists()) {
            $warnings[] = 'Nenhum registro profissional cadastrado.';
        }

        $now = Carbon::now();
        $hasOngoingTimeBlock = $professional->timeBlocks()
            ->where('status', RecordStatus::Active)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->exists();

        if ($hasOngoingTimeBlock) {
            $warnings[] = 'Há uma ausência em andamento.';
        }

        $isOperational = $reasons === [];

        return new ProfessionalOperationalStatusData(
            isOperational: $isOperational,
            status: $isOperational ? ProfessionalOperationalStatus::Operational : ProfessionalOperationalStatus::Incomplete,
            reasons: $reasons,
            warnings: $warnings,
        );
    }
}
