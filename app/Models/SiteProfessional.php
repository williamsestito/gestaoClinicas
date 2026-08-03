<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteProfessionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ficha de profissional exibida na vitrine pública ("Equipe"). Cadastro de
 * marketing/apresentação — não tem relação com o vínculo de acesso ao
 * sistema (User/OrganizationMembership), que é um conceito diferente.
 *
 * Pode opcionalmente referenciar um App\Models\Professional (cadastro
 * operacional) via `professional_id` — vínculo puramente informativo, que
 * nunca torna os dois cadastros um só: o conteúdo público continua
 * existindo e sendo editável independente do vínculo. Ver
 * docs/modules/public-integration.md.
 *
 * @property int $id
 * @property string|null $professional_id
 * @property string $name
 * @property string|null $role_title
 * @property string|null $specialty
 * @property string|null $professional_register
 * @property string|null $bio
 * @property string|null $photo_path
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $linkedin_url
 * @property int $order
 * @property bool $is_active
 * @property-read Professional|null $professional
 */
class SiteProfessional extends Model
{
    /** @use HasFactory<SiteProfessionalFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'role_title',
        'specialty',
        'professional_register',
        'bio',
        'photo_path',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
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

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
