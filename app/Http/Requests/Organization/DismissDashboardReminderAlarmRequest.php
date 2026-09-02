<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\RecordStatus;
use App\Models\ProfessionalDashboardReminder;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Só autoriza quando o lembrete pertence ao profissional do próprio usuário
 * autenticado — mesmo padrão de DestroyDashboardReminderRequest.
 */
class DismissDashboardReminderAlarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProfessionalDashboardReminder|null $reminder */
        $reminder = $this->route('reminder');
        /** @var User|null $user */
        $user = $this->user();

        if ($reminder === null || $user === null) {
            return false;
        }

        return $user->professionals()
            ->where('status', RecordStatus::Active)
            ->whereKey($reminder->professional_id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
