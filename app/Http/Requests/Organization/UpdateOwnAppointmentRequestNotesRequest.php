<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\RecordStatus;
use App\Models\AppointmentRequest;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Autoatendimento — mesma exceção de UpdateOwnAppointmentRequestStatusRequest,
 * só para a observação interna.
 */
class UpdateOwnAppointmentRequestNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AppointmentRequest|null $appointmentRequest */
        $appointmentRequest = $this->route('appointmentRequest');
        /** @var User|null $user */
        $user = $this->user();
        $organization = app(TenantContext::class)->organization();

        if ($appointmentRequest === null || $user === null || $appointmentRequest->professional_id === null) {
            return false;
        }

        // Checagem explícita de organização — ver
        // UpdateOwnAppointmentRequestStatusRequest::authorize().
        if ($organization === null || $appointmentRequest->organization_id !== $organization->id) {
            return false;
        }

        return $user->professionals()
            ->where('status', RecordStatus::Active)
            ->whereKey($appointmentRequest->professional_id)
            ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
