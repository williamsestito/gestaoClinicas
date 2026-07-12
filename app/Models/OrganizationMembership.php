<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationMembershipStatus;
use Database\Factories\OrganizationMembershipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property OrganizationMembershipStatus $status
 * @property bool $is_owner
 */
class OrganizationMembership extends Model
{
    /** @use HasFactory<OrganizationMembershipFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'is_owner',
        'joined_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationMembershipStatus::class,
            'is_owner' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<UnitMembership, $this> */
    public function unitMemberships(): HasMany
    {
        return $this->hasMany(UnitMembership::class);
    }
}
