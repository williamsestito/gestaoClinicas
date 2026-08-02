<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Especialidade operacional de uma clínica (ex.: Cardiologia, Fisioterapia
 * esportiva). Cadastro de domínio próprio da organização — sem relação com
 * o campo livre `specialty` de App\Models\SiteProfessional (vitrine
 * pública, single-tenant).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property RecordStatus $status
 * @property int $display_order
 * @property Carbon|null $deleted_at
 */
class Specialty extends Model
{
    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'status',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ProfessionalSpecialty, $this> */
    public function professionalLinks(): HasMany
    {
        return $this->hasMany(ProfessionalSpecialty::class);
    }
}
