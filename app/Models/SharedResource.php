<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\SharedResourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Recurso compartilhado (sala/equipamento) — Etapa 3.3 do roadmap. Pertence
 * a exatamente uma unidade, nunca à organização inteira. Chamado
 * "SharedResource", não "Resource": esse nome colide com o pseudo-tipo
 * `resource` do PHP/PHPDoc (usado por handles de arquivo/stream) — o Pint
 * (fixer `phpdoc_types`) normaliza qualquer variação de caixa de "resource"
 * para minúsculo em blocos de generics (`@return HasMany<Resource, ...>`),
 * corrompendo a referência à classe real.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string $name
 * @property string|null $type
 * @property RecordStatus $status
 * @property Carbon|null $deleted_at
 */
class SharedResource extends Model
{
    /** @use HasFactory<SharedResourceFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'resources';

    protected $fillable = [
        'organization_id',
        'unit_id',
        'name',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
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

    /** @return BelongsToMany<Appointment, $this> */
    public function appointments(): BelongsToMany
    {
        // Chave de pivô explícita — ver Appointment::resources().
        return $this->belongsToMany(Appointment::class, 'appointment_resource', 'resource_id', 'appointment_id')->withTimestamps();
    }
}
