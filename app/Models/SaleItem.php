<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleItemType;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Item de uma venda (Etapa 5 — Comercial). `unit_price_cents`/
 * `final_price_cents` são sempre um retrato do momento da venda — nunca
 * relidos do produto/serviço depois de criados. Sem soft delete: um item
 * de venda confirmada nunca é apagado (só a venda inteira muda de
 * status).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sale_id
 * @property SaleItemType $item_type
 * @property string|null $service_id
 * @property string|null $product_id
 * @property int|null $session_count
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $discount_percentage
 * @property int $final_price_cents
 * @property bool $requires_approval
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $approval_justification
 */
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'sale_id',
        'item_type',
        'service_id',
        'product_id',
        'session_count',
        'quantity',
        'unit_price_cents',
        'discount_percentage',
        'final_price_cents',
        'requires_approval',
        'approved_by',
        'approved_at',
        'approval_justification',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => SaleItemType::class,
            'session_count' => 'integer',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'discount_percentage' => 'integer',
            'final_price_cents' => 'integer',
            'requires_approval' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->requires_approval && $this->approved_at === null;
    }
}
