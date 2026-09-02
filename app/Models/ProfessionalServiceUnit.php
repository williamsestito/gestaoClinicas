<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProfessionalServiceUnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Unidade específica onde um vínculo profissional-serviço se aplica — usado
 * somente quando professional_service.unit_scope = SelectedUnits.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_service_id
 * @property string $unit_id
 * @property Carbon|null $deleted_at
 */
class ProfessionalServiceUnit extends Model
{
    /** @use HasFactory<ProfessionalServiceUnitFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'professional_service_unit';

    protected $fillable = [
        'organization_id',
        'professional_service_id',
        'unit_id',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ProfessionalService, $this> */
    public function professionalService(): BelongsTo
    {
        return $this->belongsTo(ProfessionalService::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
