<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentRequestStatus;
use Database\Factories\AppointmentRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Solicitação de agendamento enviada pela landing pública (lead). Nunca
 * representa um horário confirmado — a clínica confirma manualmente e
 * atualiza o status (ver App\Enums\AppointmentRequestStatus). Não existe
 * agenda/disponibilidade real no sistema ainda.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string|null $unit_id
 * @property int|null $service_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $preferred_period
 * @property string|null $notes
 * @property AppointmentRequestStatus $status
 * @property Carbon $terms_accepted_at
 */
class AppointmentRequest extends Model
{
    /** @use HasFactory<AppointmentRequestFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'service_id',
        'name',
        'phone',
        'email',
        'preferred_period',
        'notes',
        'status',
        'terms_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentRequestStatus::class,
            'terms_accepted_at' => 'datetime',
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

    /** @return BelongsTo<SiteService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(SiteService::class, 'service_id');
    }
}
