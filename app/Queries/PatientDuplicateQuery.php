<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\Patient;
use App\Support\Documents\Document;
use Illuminate\Support\Collection;

/**
 * Busca de possíveis duplicados por igualdade EXATA — nunca por
 * similaridade/nome parecido (o projeto evita isso deliberadamente, ver
 * PublicProfessionalQueryTest::it('never deduplicates by name alone')).
 * Usada só como aviso antes de criar um paciente (seção 5.4 do documento de
 * visão) — nunca bloqueia a criação. Mesclagem de fato fica para a Etapa 2.2.
 */
final class PatientDuplicateQuery
{
    /** @return Collection<int, Patient> */
    public function search(
        Organization $organization,
        ?string $document,
        ?string $phone,
        ?string $email,
        ?string $name,
        ?string $birthDate,
    ): Collection {
        $document = $document !== null && $document !== '' ? Document::onlyDigits($document) : null;
        $hasAnyCriteria = false;

        $query = $organization->patients()->where(function ($outer) use ($document, $phone, $email, $name, $birthDate, &$hasAnyCriteria) {
            if ($document !== null) {
                $outer->orWhere('document', $document);
                $hasAnyCriteria = true;
            }

            if ($phone !== null && $phone !== '') {
                $outer->orWhere('phone', $phone)->orWhere('whatsapp', $phone);
                $hasAnyCriteria = true;
            }

            if ($email !== null && $email !== '') {
                $outer->orWhere('email', $email);
                $hasAnyCriteria = true;
            }

            if ($name !== null && $name !== '' && $birthDate !== null && $birthDate !== '') {
                $outer->orWhere(fn ($q) => $q->where('name', $name)->where('birth_date', $birthDate));
                $hasAnyCriteria = true;
            }
        });

        if (! $hasAnyCriteria) {
            return collect();
        }

        return $query->limit(10)->get();
    }
}
