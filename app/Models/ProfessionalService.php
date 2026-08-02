<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfessionalServiceUnitScope;
use App\Enums\RecordStatus;
use Database\Factories\ProfessionalServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vínculo explícito entre um profissional e um serviço que ele executa.
 * Duração/preço/buffers específicos são opcionais — quando ausentes
 * (`null`), os valores efetivos herdam os padrões de App\Models\Service
 * (ver effective*()). Sem agenda nem disponibilidade nesta etapa. No
 * máximo um vínculo ativo por par profissional/serviço, garantido por
 * índice único parcial.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string $service_id
 * @property int|null $custom_duration_minutes
 * @property int|null $custom_price_cents
 * @property int|null $custom_buffer_before_minutes
 * @property int|null $custom_buffer_after_minutes
 * @property ProfessionalServiceUnitScope $unit_scope
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class ProfessionalService extends Model
{
    /** @use HasFactory<ProfessionalServiceFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'professional_service';

    protected $fillable = [
        'organization_id',
        'professional_id',
        'service_id',
        'custom_duration_minutes',
        'custom_price_cents',
        'custom_buffer_before_minutes',
        'custom_buffer_after_minutes',
        'unit_scope',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'custom_duration_minutes' => 'integer',
            'custom_price_cents' => 'integer',
            'custom_buffer_before_minutes' => 'integer',
            'custom_buffer_after_minutes' => 'integer',
            'unit_scope' => ProfessionalServiceUnitScope::class,
            'status' => RecordStatus::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<ProfessionalServiceUnit, $this> */
    public function unitLinks(): HasMany
    {
        return $this->hasMany(ProfessionalServiceUnit::class);
    }

    /** Duração efetiva: valor específico quando definido, senão o padrão do serviço. */
    public function effectiveDurationMinutes(): int
    {
        return $this->custom_duration_minutes ?? $this->service->default_duration_minutes;
    }

    /** Preço efetivo: `null` só quando não há valor específico NEM padrão — zero é um preço explícito válido, nunca confundido com ausência. */
    public function effectivePriceCents(): ?int
    {
        return $this->custom_price_cents ?? $this->service->default_price_cents;
    }

    public function effectiveBufferBeforeMinutes(): int
    {
        return $this->custom_buffer_before_minutes ?? $this->service->buffer_before_minutes;
    }

    public function effectiveBufferAfterMinutes(): int
    {
        return $this->custom_buffer_after_minutes ?? $this->service->buffer_after_minutes;
    }

    public function isDurationInherited(): bool
    {
        return $this->custom_duration_minutes === null;
    }

    public function isPriceInherited(): bool
    {
        return $this->custom_price_cents === null;
    }

    public function isBufferBeforeInherited(): bool
    {
        return $this->custom_buffer_before_minutes === null;
    }

    public function isBufferAfterInherited(): bool
    {
        return $this->custom_buffer_after_minutes === null;
    }

    /**
     * Unidades em que este vínculo profissional-serviço é efetivamente
     * compatível: sempre a interseção entre as unidades ativas do
     * profissional e as unidades disponíveis do serviço, restrita ainda
     * mais pela seleção explícita quando `unit_scope = SelectedUnits`.
     * Nunca ambíguo — `None` sempre resolve para uma lista vazia.
     *
     * @return Collection<int, string>
     */
    public function compatibleUnitIds(): Collection
    {
        if ($this->unit_scope === ProfessionalServiceUnitScope::None) {
            return collect();
        }

        $intersection = $this->professional->activeUnitIds()
            ->intersect($this->service->availableUnitIds());

        if ($this->unit_scope === ProfessionalServiceUnitScope::SelectedUnits) {
            return $intersection->intersect($this->unitLinks()->pluck('unit_id'));
        }

        return $intersection;
    }

    public function hasNoCompatibleUnits(): bool
    {
        return $this->compatibleUnitIds()->isEmpty();
    }
}
