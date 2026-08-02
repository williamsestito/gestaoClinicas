<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceUnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Unidade onde um serviço está disponível — usado apenas quando
 * services.unit_availability_scope = ServiceAvailabilityScope::SelectedUnits.
 * No máximo um vínculo ativo por par serviço/unidade.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string $unit_id
 * @property Carbon|null $deleted_at
 */
class ServiceUnit extends Model
{
    /** @use HasFactory<ServiceUnitFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'service_unit';

    protected $fillable = [
        'organization_id',
        'service_id',
        'unit_id',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
