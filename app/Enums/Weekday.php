<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dia da semana para jornadas recorrentes — mesma convenção numérica de
 * `unit_opening_hours.day_of_week` (0 domingo a 6 sábado), agora tipada.
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Sunday => 'Domingo',
            self::Monday => 'Segunda-feira',
            self::Tuesday => 'Terça-feira',
            self::Wednesday => 'Quarta-feira',
            self::Thursday => 'Quinta-feira',
            self::Friday => 'Sexta-feira',
            self::Saturday => 'Sábado',
        };
    }

    /**
     * Ordem de exibição de segunda a domingo.
     *
     * @return array<int, Weekday>
     */
    public static function inDisplayOrder(): array
    {
        return [
            self::Monday,
            self::Tuesday,
            self::Wednesday,
            self::Thursday,
            self::Friday,
            self::Saturday,
            self::Sunday,
        ];
    }
}
