<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CreateProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{name: string, social_name: ?string, display_name: string, email: ?string, phone: ?string, document: ?string, birth_date: ?string, bio: ?string, user_id: ?int} $attributes */
    public function handle(Organization $organization, array $attributes): Professional
    {
        try {
            $professional = $organization->professionals()->create([
                'name' => $attributes['name'],
                'social_name' => $attributes['social_name'],
                'display_name' => $attributes['display_name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'document' => $attributes['document'],
                'birth_date' => $attributes['birth_date'],
                'bio' => $attributes['bio'],
                'user_id' => $attributes['user_id'],
                'status' => RecordStatus::Active,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'document' => 'Já existe um profissional com este documento ou usuário vinculado nesta clínica.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $professional,
            after: [
                'name' => $professional->name,
                'display_name' => $professional->display_name,
                'status' => $professional->status->value,
                'document' => $professional->document,
                'linked_user_id' => $professional->user_id,
            ],
            organization: $organization,
        );

        return $professional;
    }
}
