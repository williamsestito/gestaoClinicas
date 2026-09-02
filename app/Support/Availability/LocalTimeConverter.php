<?php

declare(strict_types=1);

namespace App\Support\Availability;

use Illuminate\Support\Carbon;

/**
 * Converte data/hora civil local (no fuso de uma unidade) para instantes
 * reais em UTC — usado por App\Models\ProfessionalTimeBlock, cujos
 * `starts_at`/`ends_at` são instantes datados (diferente da jornada
 * recorrente, que guarda hora civil sem conversão). O fuso nunca vem do
 * frontend: é sempre resolvido a partir da unidade (ou da referência
 * explícita passada pelo chamador para bloqueios "todas as unidades").
 *
 * Bloqueios de dia inteiro usam semântica semiaberta `[início do dia,
 * início do dia seguinte)` no fuso de referência, evitando o uso de
 * `23:59:59` como fim de dia.
 */
final class LocalTimeConverter
{
    public static function toUtc(string $date, string $time, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $timezone)->utc();
    }

    public static function startOfLocalDayInUtc(string $date, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->utc();
    }

    public static function startOfNextLocalDayInUtc(string $date, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->addDay()->utc();
    }
}
