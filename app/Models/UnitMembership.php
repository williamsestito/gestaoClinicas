<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\UnitMembershipFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $organization_membership_id
 * @property string $unit_id
 * @property RecordStatus $status
 * @property bool $is_manager
 */
class UnitMembership extends Model
{
    /** @use HasFactory<UnitMembershipFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_membership_id',
        'unit_id',
        'status',
        'is_manager',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'is_manager' => 'boolean',
        ];
    }

    /** @return BelongsTo<OrganizationMembership, $this> */
    public function organizationMembership(): BelongsTo
    {
        return $this->belongsTo(OrganizationMembership::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
