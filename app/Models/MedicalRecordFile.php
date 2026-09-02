<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MedicalRecordFileCategory;
use Database\Factories\MedicalRecordFileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Arquivo clínico anexado a um prontuário (Seção 11 do documento de visão).
 * Visibilidade herda a mesma restrição de acesso do prontuário
 * (`App\Policies\MedicalRecordPolicy`) — nunca uma Policy própria.
 * Visualização/download são auditados explicitamente pelo controller
 * (RN-008), nunca aqui.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $unit_id
 * @property string $medical_record_id
 * @property int $uploaded_by
 * @property MedicalRecordFileCategory $category
 * @property string $original_filename
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon $created_at
 */
class MedicalRecordFile extends Model
{
    /** @use HasFactory<MedicalRecordFileFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'medical_record_id',
        'uploaded_by',
        'category',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'category' => MedicalRecordFileCategory::class,
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

    /** @return BelongsTo<MedicalRecord, $this> */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
