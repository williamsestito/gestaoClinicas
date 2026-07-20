<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteGalleryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item da galeria de imagens institucionais da landing pública.
 *
 * @property int $id
 * @property string $image_path
 * @property string|null $caption
 * @property string|null $alt_text
 * @property string|null $category
 * @property bool $is_cover
 * @property int $order
 * @property bool $is_active
 */
class SiteGalleryItem extends Model
{
    /** @use HasFactory<SiteGalleryItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'image_path',
        'caption',
        'alt_text',
        'category',
        'is_cover',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
