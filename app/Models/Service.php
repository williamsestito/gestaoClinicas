<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Serviço/procedimento operacional prestado pela clínica. Cadastro de
 * domínio próprio — sem relação com App\Models\SiteService (vitrine
 * pública, single-tenant, sem organization_id). Nenhuma lógica de agenda,
 * disponibilidade ou cobrança pertence a este Model nesta fase.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $default_duration_minutes
 * @property int $buffer_before_minutes
 * @property int $buffer_after_minutes
 * @property int|null $default_price_cents
 * @property RecordStatus $status
 * @property string|null $color
 * @property bool $is_public
 * @property bool $requires_manual_confirmation
 * @property string|null $internal_notes
 * @property Carbon|null $deleted_at
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'default_duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'default_price_cents',
        'status',
        'color',
        'is_public',
        'requires_manual_confirmation',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'default_duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'default_price_cents' => 'integer',
            'is_public' => 'boolean',
            'requires_manual_confirmation' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ProfessionalService, $this> */
    public function professionalLinks(): HasMany
    {
        return $this->hasMany(ProfessionalService::class);
    }
}
