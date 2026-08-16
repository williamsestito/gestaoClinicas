<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Serviço central de auditoria. Usado explicitamente dentro das Actions
 * (nunca via observer/evento mágico), para que cada registro de auditoria
 * seja fácil de localizar e testar a partir do código que o disparou.
 */
class AuditLogger
{
    /** @var list<string> chaves nunca gravadas em texto puro, em qualquer profundidade */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token', 'refresh_token',
        'secret', 'remember_token', 'api_key', 'recovery_codes', 'authentication_code',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'credential', 'credential_id', 'public_key', 'user_handle_secret',
    ];

    /** @var list<string> chaves mascaradas (mantém os 2 últimos caracteres), em qualquer profundidade */
    private const MASKED_KEYS = ['document', 'cpf', 'cnpj', 'registration_number'];

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function log(
        AuditAction $action,
        ?Model $auditable = null,
        array $before = [],
        array $after = [],
        ?Organization $organization = null,
        ?Unit $unit = null,
    ): AuditLog {
        return AuditLog::query()->create([
            // Guard explícito: "actor" é sempre um App\Models\User (staff) —
            // nunca um App\Models\PatientUser (guard "patient"). Bare
            // Auth::id() resolveria o guard "default" do AuthManager, que
            // testes podem trocar via actingAs($patientUser, 'patient').
            'actor_user_id' => Auth::guard('web')->id(),
            'organization_id' => $organization?->id,
            'unit_id' => $unit?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before_data' => $this->sanitize($before) ?: null,
            'after_data' => $this->sanitize($after) ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Str::limit((string) Request::userAgent(), 255, ''),
        ]);
    }

    /**
     * Sanitiza recursivamente, em qualquer profundidade (arrays aninhados
     * também são percorridos) — nunca grava senhas/tokens/segredos em
     * texto puro e sempre mascara documentos (CPF/CNPJ).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, self::SENSITIVE_KEYS, true)) {
                unset($data[$key]);

                continue;
            }

            if (in_array($lowerKey, self::MASKED_KEYS, true) && is_string($value)) {
                $data[$key] = str_repeat('*', max(strlen($value) - 2, 0)).substr($value, -2);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
