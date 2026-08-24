<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\SaleStatus;
use App\Models\Organization;
use App\Models\Sale;
use Illuminate\Pagination\LengthAwarePaginator;

final class SaleListQuery
{
    /** @return LengthAwarePaginator<int, Sale> */
    public function forOrganization(
        Organization $organization,
        ?string $patientId = null,
        ?string $unitId = null,
        ?SaleStatus $status = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $organization->sales()
            ->with(['patient:id,name,preferred_name', 'unit:id,name'])
            ->latest('created_at');

        if ($patientId !== null) {
            $query->where('patient_id', $patientId);
        }

        if ($unitId !== null) {
            $query->where('unit_id', $unitId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
