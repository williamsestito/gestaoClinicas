<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SaleItemType;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'organization_id' => fn (array $attributes) => Sale::query()->findOrFail((string) $attributes['sale_id'])->organization_id,
            'item_type' => SaleItemType::Service,
            'service_id' => fn (array $attributes) => Service::factory()->for(
                Organization::query()->findOrFail((string) $attributes['organization_id']),
            )->create()->id,
            'product_id' => null,
            'session_count' => null,
            'quantity' => 1,
            'unit_price_cents' => 10000,
            'discount_percentage' => 0,
            'final_price_cents' => 10000,
            'requires_approval' => false,
            'approved_by' => null,
            'approved_at' => null,
            'approval_justification' => null,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percentage' => 50,
            'final_price_cents' => (int) round(($attributes['unit_price_cents'] ?? 10000) * 0.5),
            'requires_approval' => true,
        ]);
    }
}
