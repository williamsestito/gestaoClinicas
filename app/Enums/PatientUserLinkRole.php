<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papel do vínculo entre uma conta de portal (App\Models\PatientUser) e um
 * paciente que ela gerencia — "self" quando o titular da conta é o próprio
 * paciente, "dependent" quando é um responsável gerenciando outra pessoa.
 */
enum PatientUserLinkRole: string
{
    case Self = 'self';
    case Dependent = 'dependent';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Titular',
            self::Dependent => 'Dependente',
        };
    }
}
