<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MedicalRecordAddendumFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Correção de um prontuário já finalizado (RN-007) — só criado, nunca
 * atualizado ou excluído (não existem rotas de update/destroy). O autor do
 * adendo (`professional_id`) pode ser diferente do autor original do
 * prontuário.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string $medical_record_id
 * @property string $professional_id
 * @property string $body
 * @property Carbon $created_at
 */
class MedicalRecordAddendum extends Model
{
    /** @use HasFactory<MedicalRecordAddendumFactory> */
    use HasFactory, HasUlids;

    /**
     * Sem esta declaração explícita, o Eloquent pluralizaria
     * "MedicalRecordAddendum" para "medical_record_addendums" em vez do
     * plural correto "medical_record_addenda" usado na migration.
     */
    protected $table = 'medical_record_addenda';

    protected $fillable = [
        'organization_id',
        'unit_id',
        'medical_record_id',
        'professional_id',
        'body',
    ];

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

    /** @return BelongsTo<MedicalRecord, $this> */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
