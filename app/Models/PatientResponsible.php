<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PatientResponsibleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contato de responsável por um paciente (legal, financeiro e/ou
 * representante autorizado — os três não são exclusivos entre si). Não é
 * outro Patient: ver decisão registrada em docs/modules/patients.md.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $patient_id
 * @property string $name
 * @property string|null $document dígitos apenas, sem máscara
 * @property string $phone
 * @property string $relationship
 * @property bool $is_legal_guardian
 * @property bool $is_financial_responsible
 * @property bool $is_authorized_representative
 */
class PatientResponsible extends Model
{
    /** @use HasFactory<PatientResponsibleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'patient_id',
        'name',
        'document',
        'phone',
        'relationship',
        'is_legal_guardian',
        'is_financial_responsible',
        'is_authorized_representative',
    ];

    protected function casts(): array
    {
        return [
            'is_legal_guardian' => 'boolean',
            'is_financial_responsible' => 'boolean',
            'is_authorized_representative' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
