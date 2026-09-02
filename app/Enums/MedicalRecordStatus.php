<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida do prontuário (RN-007 do documento de visão): `Draft` é
 * editável livremente pelo autor; ao virar `Finalized`, os campos clínicos
 * nunca mais são atualizados diretamente — correções passam a exigir um
 * `App\Models\MedicalRecordAddendum` novo.
 */
enum MedicalRecordStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Finalized => 'Finalizado',
        };
    }
}
