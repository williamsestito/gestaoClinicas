<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ProfessionalServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vínculo explícito entre um profissional e um serviço que ele executa.
 * Duração/preço/buffers específicos são opcionais — quando ausentes, as
 * próximas fases devem usar os valores padrão de App\Models\Service. Sem
 * agenda nem disponibilidade nesta etapa. No máximo um vínculo ativo por
 * par profissional/serviço, garantido por índice único parcial.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string $service_id
 * @property int|null $custom_duration_minutes
 * @property int|null $custom_price_cents
 * @property int|null $custom_buffer_before_minutes
 * @property int|null $custom_buffer_after_minutes
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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'custom_duration_minutes' => 'integer',
            'custom_price_cents' => 'integer',
            'custom_buffer_before_minutes' => 'integer',
            'custom_buffer_after_minutes' => 'integer',
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
}
