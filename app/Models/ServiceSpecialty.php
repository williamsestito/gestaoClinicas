<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceSpecialtyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vínculo explícito entre um serviço e uma especialidade que o executa. No
 * máximo um vínculo ativo por par serviço/especialidade — garantido por
 * índice único parcial na migration.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string $specialty_id
 * @property Carbon|null $deleted_at
 */
class ServiceSpecialty extends Model
{
    /** @use HasFactory<ServiceSpecialtyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'service_specialty';

    protected $fillable = [
        'organization_id',
        'service_id',
        'specialty_id',
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

    /** @return BelongsTo<Specialty, $this> */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
