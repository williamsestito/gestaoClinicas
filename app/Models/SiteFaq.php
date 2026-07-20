<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteFaqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pergunta frequente exibida na landing pública.
 *
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property string|null $category
 * @property int $order
 * @property bool $is_active
 */
class SiteFaq extends Model
{
    /** @use HasFactory<SiteFaqFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'question',
        'answer',
        'category',
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
