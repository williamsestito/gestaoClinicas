<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DashboardReminderColor;
use Database\Factories\ProfessionalDashboardReminderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lembrete tipo post-it no dashboard do profissional — conteúdo pessoal,
 * nunca um registro de negócio (ver migration para o porquê de permitir
 * exclusão física).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $professional_id
 * @property string $body
 * @property DashboardReminderColor $color
 * @property Carbon|null $alarm_at
 * @property Carbon $created_at
 */
class ProfessionalDashboardReminder extends Model
{
    /** @use HasFactory<ProfessionalDashboardReminderFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'professional_id',
        'body',
        'color',
        'alarm_at',
    ];

    protected function casts(): array
    {
        return [
            'color' => DashboardReminderColor::class,
            'alarm_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
