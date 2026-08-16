<?php

declare(strict_types=1);

namespace App\Support\Patients;

use Illuminate\Validation\Validator;

/**
 * Endereço do paciente é opcional como bloco inteiro (diferente de Unit,
 * onde é sempre obrigatório), mas se qualquer campo além do CEP — que é só
 * "recomendado" (seção 3.2 do documento de visão) — for preenchido, os
 * campos obrigatórios do bloco passam a ser exigidos juntos. Reaproveitado
 * por CreatePatientRequest e UpdatePatientRequest.
 */
final class PatientAddressBlockGuard
{
    /** @param array<string, mixed> $address */
    public static function assertCompleteOrEmpty(array $address, Validator $validator): void
    {
        $required = ['street', 'number', 'neighborhood', 'city', 'state'];

        $anyFilled = collect($required)->contains(fn (string $field) => filled($address[$field] ?? null));

        if (! $anyFilled) {
            return;
        }

        foreach ($required as $field) {
            if (blank($address[$field] ?? null)) {
                $validator->errors()->add("address.{$field}", 'Ao informar o endereço, preencha rua, número, bairro, cidade e UF.');
            }
        }
    }
}
