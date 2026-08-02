<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ProfessionalSpecialtyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vínculo explícito entre um profissional e uma especialidade. No máximo um
 * vínculo ativo (não excluído) por par profissional/especialidade, e no
 * máximo uma especialidade principal ativa por profissional — garantido por
 * índices únicos parciais na migration.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string $specialty_id
 * @property bool $is_primary
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class ProfessionalSpecialty extends Model
{
    /** @use HasFactory<ProfessionalSpecialtyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'professional_specialty';

    protected $fillable = [
        'organization_id',
        'professional_id',
        'specialty_id',
        'is_primary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
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

    /** @return BelongsTo<Specialty, $this> */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
