<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Serviço/tratamento exibido na vitrine pública da clínica. Cadastro leve,
 * apenas para apresentação — não é um módulo clínico (sem agenda,
 * disponibilidade ou prontuário, que ficam para fase futura).
 *
 * @property int $id
 * @property string $name
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $image_path
 * @property string|null $icon
 * @property string|null $category
 * @property int|null $duration_minutes
 * @property int|null $starting_price_cents
 * @property string|null $cta_text
 * @property bool $is_featured
 * @property int $order
 * @property bool $is_active
 */
class SiteService extends Model
{
    /** @use HasFactory<SiteServiceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'short_description',
        'description',
        'image_path',
        'icon',
        'category',
        'duration_minutes',
        'starting_price_cents',
        'cta_text',
        'is_featured',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'starting_price_cents' => 'integer',
            'is_featured' => 'boolean',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<AppointmentRequest, $this> */
    public function appointmentRequests(): HasMany
    {
        return $this->hasMany(AppointmentRequest::class, 'service_id');
    }
}
