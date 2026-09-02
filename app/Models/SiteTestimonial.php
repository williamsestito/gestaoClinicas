<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteTestimonialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Depoimento de paciente/cliente exibido na landing pública. A foto é
 * sempre opcional — a interface nunca deve depender dela.
 *
 * @property int $id
 * @property string $author_name
 * @property string|null $author_photo_path
 * @property int|null $rating
 * @property string $content
 * @property int|null $related_service_id
 * @property bool $is_featured
 * @property int $order
 * @property bool $is_active
 */
class SiteTestimonial extends Model
{
    /** @use HasFactory<SiteTestimonialFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_name',
        'author_photo_path',
        'rating',
        'content',
        'related_service_id',
        'is_featured',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<SiteService, $this> */
    public function relatedService(): BelongsTo
    {
        return $this->belongsTo(SiteService::class, 'related_service_id');
    }
}
