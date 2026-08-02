<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ProfessionalUnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vínculo explícito entre um profissional e uma unidade — representa ONDE o
 * profissional ATUA clinicamente. Não confundir com App\Models\UnitMembership,
 * que representa onde um USUÁRIO pode ACESSAR o sistema; os dois conceitos
 * são independentes e não devem ser fundidos. No máximo um vínculo ativo por
 * par profissional/unidade, e no máximo uma unidade principal ativa por
 * profissional — garantido por índices únicos parciais na migration.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string $unit_id
 * @property bool $is_primary
 * @property RecordStatus $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $deleted_at
 */
class ProfessionalUnit extends Model
{
    /** @use HasFactory<ProfessionalUnitFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'professional_unit';

    protected $fillable = [
        'organization_id',
        'professional_id',
        'unit_id',
        'is_primary',
        'status',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'status' => RecordStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
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

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
