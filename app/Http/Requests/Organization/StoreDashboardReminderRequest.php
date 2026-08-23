<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\DashboardReminderColor;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Autoatendimento — só cria um lembrete quando o usuário autenticado tem um
 * cadastro de profissional ativo na organização ativa (mesmo padrão de
 * UpdateOwnAppointmentRequestStatusRequest); o vínculo em si é resolvido de
 * novo no Controller a partir do usuário, nunca aceito do frontend.
 */
class StoreDashboardReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        if ($organization === null) {
            return false;
        }

        /** @var User $user */
        $user = $this->user();

        return $user->professionals()
            ->where('organization_id', $organization->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:280'],
            'color' => ['required', Rule::enum(DashboardReminderColor::class)],
            // Já convertido para UTC no cliente (Date::toISOString(), a
            // partir do horário local do navegador) — checado só no cliente
            // enquanto o dashboard estiver aberto, nunca dispara nada no
            // servidor (ver ProfessionalDashboard.vue).
            'alarm_at' => ['nullable', 'date'],
        ];
    }
}
