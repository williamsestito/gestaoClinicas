<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Produto vendável (Etapa 5 — Comercial). Sem campos de estoque/lote/
 * validade — só passam a existir quando o módulo de Estoque (Etapa 7)
 * chegar de verdade.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $code
 * @property string|null $barcode
 * @property string $unit_of_measure
 * @property int|null $cost_cents
 * @property int|null $margin_percentage
 * @property int|null $price_cents
 * @property int|null $max_discount_percentage
 * @property RecordStatus $status
 * @property string|null $internal_notes
 * @property Carbon|null $deleted_at
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'barcode',
        'unit_of_measure',
        'cost_cents',
        'margin_percentage',
        'price_cents',
        'max_discount_percentage',
        'status',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'cost_cents' => 'integer',
            'margin_percentage' => 'integer',
            'price_cents' => 'integer',
            'max_discount_percentage' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<SaleItem, $this> */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
