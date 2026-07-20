<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Convite para um usuário ingressar em uma organização. O token nunca é
 * armazenado em texto puro — só o hash (`token_hash`). Ver
 * App\Actions\Organization\InviteUserAction.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property string|null $role_id
 * @property int $invited_by
 * @property string $token_hash
 * @property InvitationStatus $status
 * @property Carbon|null $accepted_at
 * @property Carbon $expires_at
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'email',
        'role_id',
        'invited_by',
        'token_hash',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsToMany<Unit, $this> */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'invitation_units');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->isPast();
    }
}
