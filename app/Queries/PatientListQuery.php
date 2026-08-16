<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Support\Documents\Document;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Diferente de Specialty/Service/Professional (listagem inteira sem
 * paginação, filtrada no cliente), a listagem de pacientes é paginada e
 * filtrada no servidor — volume de pacientes tende a crescer bem além do de
 * profissionais (ver docs/roadmap.md, Etapa 2).
 */
final class PatientListQuery
{
    /** @return LengthAwarePaginator<int, Patient> */
    public function forOrganization(
        Organization $organization,
        ?string $search,
        ?RecordStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $organization->patients()->withTrashed()->orderBy('name');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== null && $search !== '') {
            $digits = Document::onlyDigits($search);

            $query->where(function ($inner) use ($search, $digits) {
                $inner->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");

                if ($digits !== '') {
                    $inner->orWhere('document', 'like', "%{$digits}%");
                }
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
