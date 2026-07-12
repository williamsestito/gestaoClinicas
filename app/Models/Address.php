<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Endereço reutilizável (relação polimórfica) para LegalEntity, Unit e,
 * futuramente, pacientes. Os tipos polimórficos usam um morph map explícito
 * (ver App\Providers\AppServiceProvider) — nunca o nome completo da classe.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $postal_code
 * @property string $state
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'postal_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'country',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return MorphTo<Model, $this> */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
