<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Registro de auditoria, somente leitura após criado: não possui
 * `updated_at` e `update()`/`delete()` são bloqueados na aplicação.
 *
 * @property string $id
 * @property int|null $actor_user_id
 * @property string|null $organization_id
 * @property string|null $unit_id
 * @property AuditAction $action
 * @property array<string, mixed>|null $before_data
 * @property array<string, mixed>|null $after_data
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'organization_id',
        'unit_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before_data',
        'after_data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before_data' => 'array',
            'after_data' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
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

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Registros de auditoria não podem ser editados.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Registros de auditoria não podem ser excluídos.');
    }
}
