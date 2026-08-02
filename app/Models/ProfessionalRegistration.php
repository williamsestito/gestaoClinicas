<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Support\Documents\BrazilianState;
use Database\Factories\ProfessionalRegistrationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Registro em conselho de classe (ex.: CRM/SP 123456) de um profissional.
 * Um profissional pode ter mais de um registro; no máximo um ativo pode ser
 * marcado como principal (`is_primary`, garantido por índice único parcial).
 * `council` é texto livre — o catálogo de conselhos varia por tipo de
 * profissional e um enum fechado seria frágil (decisão da Etapa 2.0).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string $council
 * @property string|null $registration_type
 * @property string $registration_number
 * @property BrazilianState $state
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property RecordStatus $status
 * @property bool $is_primary
 * @property string|null $internal_notes
 * @property Carbon|null $deleted_at
 */
class ProfessionalRegistration extends Model
{
    /** @use HasFactory<ProfessionalRegistrationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'professional_id',
        'council',
        'registration_type',
        'registration_number',
        'state',
        'issued_at',
        'expires_at',
        'status',
        'is_primary',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'state' => BrazilianState::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'status' => RecordStatus::class,
            'is_primary' => 'boolean',
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
}
