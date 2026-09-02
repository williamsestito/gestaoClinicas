<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Finaliza um convite: cria (ou reaproveita) o usuário, vincula à
 * organização com o papel e as unidades definidos no convite, e marca o
 * convite como aceito. Tudo em uma única transação.
 */
class AcceptInvitationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Invitation $invitation, string $name, string $password): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => 'Este convite não é mais válido. Solicite um novo convite ao administrador da clínica.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::query()->where('email', $invitation->email)->first();

            if (! $user) {
                // is_active/is_platform_admin/email_verified_at nao estao em
                // $fillable de proposito (flags sensiveis) — forceFill e o
                // unico ponto autorizado a defini-las na criacao.
                $user = new User(['name' => $name, 'email' => $invitation->email, 'password' => Hash::make($password)]);
                $user->forceFill([
                    'is_active' => true,
                    'is_platform_admin' => false,
                    'email_verified_at' => now(),
                ])->save();
            }

            $membership = $invitation->organization->memberships()->create([
                'user_id' => $user->id,
                'status' => OrganizationMembershipStatus::Active,
                'is_owner' => false,
                'role_id' => $invitation->role_id,
                'joined_at' => now(),
                'created_by' => $invitation->invited_by,
            ]);

            $units = $invitation->units()->get();

            foreach ($units as $unit) {
                $membership->unitMemberships()->create([
                    'unit_id' => $unit->id,
                    'status' => RecordStatus::Active,
                    'is_manager' => false,
                    'is_primary' => $units->count() === 1,
                ]);
            }

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $membership,
                after: ['user_id' => $user->id, 'role_id' => $invitation->role_id],
                organization: $invitation->organization,
            );

            return $user;
        });
    }
}
