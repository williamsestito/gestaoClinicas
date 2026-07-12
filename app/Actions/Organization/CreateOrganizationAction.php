<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Support\Auditing\AuditLogger;
use App\Support\SlugGenerator;

class CreateOrganizationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(string $name): Organization
    {
        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => SlugGenerator::unique($name, fn (string $slug) => Organization::query()->where('slug', $slug)),
            'status' => OrganizationStatus::Active,
            'default_timezone' => config('business.default_timezone'),
            'default_currency' => config('business.default_currency'),
            'locale' => config('business.default_locale'),
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $organization,
            after: $organization->only(['name', 'slug', 'status']),
            organization: $organization,
        );

        return $organization;
    }
}
