<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Permite ao administrador redefinir a senha do usuário de acesso vinculado
 * a um profissional. Mesma exceção deliberada registrada em
 * CreateProfessionalAction ("nunca um usuário com senha definida
 * diretamente por um administrador") — extensão natural dela: se o
 * administrador já define a senha na criação, também precisa poder
 * redefini-la depois (ex.: o profissional esqueceu, ou o acesso original
 * nunca chegou a ser usado).
 */
class ResetProfessionalUserPasswordAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional, string $password): void
    {
        $user = $professional->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'Este profissional não tem um usuário de acesso vinculado.',
            ]);
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $professional,
            after: ['password_reset_for_user_id' => $user->id],
            organization: $professional->organization,
        );
    }
}
