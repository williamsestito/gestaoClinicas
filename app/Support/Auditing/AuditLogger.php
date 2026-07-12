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
    /** @var list<string> chaves nunca gravadas em texto puro */
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'remember_token', 'api_key'];

    /** @var list<string> chaves mascaradas (mantém os 2 últimos caracteres) */
    private const MASKED_KEYS = ['document'];

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
            'actor_user_id' => Auth::id(),
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
            }
        }

        return $data;
    }
}
