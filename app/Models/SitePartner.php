<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SitePartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Convênio ou parceiro exibido na landing pública (logo + nome + link
 * opcional). Mesmo padrão de coleção que SiteBenefit/SiteGalleryItem.
 *
 * @property int $id
 * @property string $name
 * @property string|null $logo_path
 * @property string|null $url
 * @property int $order
 * @property bool $is_active
 */
class SitePartner extends Model
{
    /** @use HasFactory<SitePartnerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'logo_path',
        'url',
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
