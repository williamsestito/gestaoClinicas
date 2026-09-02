<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Recorrência (Etapa 3.3 do roadmap) — MVP: só semanal (mesmo dia da
 * semana/horário), sem RRULE genérico. Cria N `Appointment` independentes
 * de uma vez (nenhuma "regra viva" que gera ocorrências futuras) — cada um
 * já nasce como uma linha normal, agrupada só visualmente por
 * `recurrence_group_id`. Reaproveita `CreateAppointmentAction` em loop
 * (guard, auditoria, pacote/recurso opcionais — tudo já validado lá); uma
 * ocorrência que conflita é pulada, sem abortar as demais.
 */
class CreateRecurringAppointmentSeriesAction
{
    public const MAX_OCCURRENCES = 52;

    public function __construct(private readonly CreateAppointmentAction $createAppointmentAction) {}

    /**
     * @param  list<string>  $resourceIds
     * @return array{created: list<Appointment>, skipped: list<array{date: string, reason: string}>}
     */
    public function handle(
        Organization $organization,
        Unit $unit,
        Professional $professional,
        Patient $patient,
        Service $service,
        CarbonInterface $firstStartsAt,
        CarbonInterface $firstEndsAt,
        int $occurrences,
        ?string $notes = null,
        ?AppointmentRequest $sourceRequest = null,
        array $resourceIds = [],
        ?SessionPackage $sessionPackage = null,
    ): array {
        if ($occurrences < 2 || $occurrences > self::MAX_OCCURRENCES) {
            throw ValidationException::withMessages([
                'recurrence_weeks' => 'A recorrência deve ter entre 2 e '.self::MAX_OCCURRENCES.' semanas.',
            ]);
        }

        $recurrenceGroupId = (string) Str::ulid();
        $created = [];
        $skipped = [];

        for ($i = 0; $i < $occurrences; $i++) {
            $startsAt = $firstStartsAt->copy()->addWeeks($i);
            $endsAt = $firstEndsAt->copy()->addWeeks($i);

            try {
                $created[] = $this->createAppointmentAction->handle(
                    $organization,
                    $unit,
                    $professional,
                    $patient,
                    $service,
                    $startsAt,
                    $endsAt,
                    $notes,
                    // O lead só é convertido pela primeira ocorrência —
                    // convertê-lo N vezes não faz sentido (idempotência já
                    // bloqueia a 2ª tentativa de qualquer forma).
                    $i === 0 ? $sourceRequest : null,
                    $resourceIds,
                    $sessionPackage,
                    $recurrenceGroupId,
                );
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'date' => $startsAt->toIso8601String(),
                    'reason' => collect($exception->errors())->flatten()->first() ?? 'Conflito de agenda.',
                ];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
