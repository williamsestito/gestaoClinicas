<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro do que foi vendido, a que preço e com que desconto (Etapa 5 —
 * Comercial). Não rastreia cobrança/parcela/recebimento nem passa por
 * caixa — ver docs/modules/sales.md. Sem soft delete: cancelamento é
 * status, nunca exclusão (RN-009/RN-017).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string $legal_entity_id
 * @property string $patient_id
 * @property string|null $professional_id
 * @property string|null $appointment_id
 * @property SaleStatus $status
 * @property int $subtotal_cents
 * @property int $discount_total_cents
 * @property int $total_cents
 * @property string|null $cancellation_reason
 * @property int $created_by
 */
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'legal_entity_id',
        'patient_id',
        'professional_id',
        'appointment_id',
        'status',
        'subtotal_cents',
        'discount_total_cents',
        'total_cents',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'subtotal_cents' => 'integer',
            'discount_total_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Verdadeiro se algum item ainda aguarda aprovação de desconto. */
    public function hasPendingApprovalItems(): bool
    {
        return $this->items()->where('requires_approval', true)->whereNull('approved_at')->exists();
    }
}
