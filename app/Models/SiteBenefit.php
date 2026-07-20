<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteBenefitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item da seção "Diferenciais" da landing pública.
 *
 * @property int $id
 * @property string|null $icon
 * @property string $title
 * @property string|null $description
 * @property int $order
 * @property bool $is_active
 */
class SiteBenefit extends Model
{
    /** @use HasFactory<SiteBenefitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'icon',
        'title',
        'description',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
