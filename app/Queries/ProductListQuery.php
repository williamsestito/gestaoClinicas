<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\Product;

final class ProductListQuery
{
    /**
     * @return array<int, array{id: string, name: string, code: string, unit_of_measure: string, price_cents: int|null, status: string, deleted_at: string|null, updated_at: string|null}>
     */
    public function forOrganization(Organization $organization): array
    {
        return $organization->products()
            ->withTrashed()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'unit_of_measure' => $product->unit_of_measure,
                'price_cents' => $product->price_cents,
                'status' => $product->status->value,
                'deleted_at' => $product->deleted_at?->toIso8601String(),
                'updated_at' => $product->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
