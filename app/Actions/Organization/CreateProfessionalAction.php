<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Exceção deliberada, registrada explicitamente por pedido do usuário: todo
 * profissional novo já nasce com um usuário de acesso próprio (papel de
 * sistema "Profissional"), com senha definida diretamente pelo
 * administrador que faz o cadastro. Isso contraria a regra geral do
 * restante da aplicação (ver App\Actions\Organization\InviteUserAction —
 * "nunca um usuário com senha definida diretamente por um administrador") —
 * mantida em todo outro ponto de criação de usuário, só afastada aqui a
 * pedido explícito do proprietário do produto, ciente do trade-off.
 */
class CreateProfessionalAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{name: string, social_name: ?string, display_name: string, email: string, phone: ?string, document: string, birth_date: ?string, bio: ?string, password: string} $attributes */
    public function handle(Organization $organization, array $attributes, User $createdBy): Professional
    {
        return DB::transaction(function () use ($organization, $attributes, $createdBy) {
            $user = new User([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
            ]);

            try {
                // is_active/is_platform_admin/email_verified_at nao estao em
                // $fillable de proposito (flags sensiveis) — forceFill e o
                // unico ponto autorizado a defini-las na criacao (mesmo
                // padrao de App\Actions\Organization\AcceptInvitationAction).
                $user->forceFill([
                    'is_active' => true,
                    'is_platform_admin' => false,
                    'email_verified_at' => now(),
                ])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe um usuário cadastrado com este e-mail.',
                ]);
            }

            $professionalRole = Role::query()
                ->where('organization_id', $organization->id)
                ->where('slug', SystemRole::Professional->value)
                ->first();

            $membership = $organization->memberships()->create([
                'user_id' => $user->id,
                'status' => OrganizationMembershipStatus::Active,
                'is_owner' => false,
                'role_id' => $professionalRole?->id,
                'joined_at' => now(),
                'created_by' => $createdBy->id,
            ]);

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
                    'user_id' => $user->id,
                    'status' => RecordStatus::Active,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe um profissional com este documento nesta clínica.',
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

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $membership,
                after: ['user_id' => $user->id, 'role_id' => $professionalRole?->id],
                organization: $organization,
            );

            return $professional;
        });
    }
}
