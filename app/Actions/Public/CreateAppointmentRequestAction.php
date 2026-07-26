<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Enums\AppointmentRequestStatus;
use App\Enums\AuditAction;
use App\Enums\OrganizationMembershipStatus;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use App\Models\Unit;
use App\Notifications\NewAppointmentRequestNotification;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Cria uma solicitação de agendamento (lead) vinda da landing pública.
 * Reaproveita uma solicitação recente idêntica em vez de duplicá-la (mesmo
 * telefone/serviço/data/período numa janela curta — duplo clique, F5 no
 * "obrigado"), audita a criação e avisa a clínica por e-mail sem deixar uma
 * falha de notificação afetar a solicitação já persistida.
 */
class CreateAppointmentRequestAction
{
    private const DUPLICATE_WINDOW_MINUTES = 10;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Organization $organization, ?Unit $unit): AppointmentRequest
    {
        $existing = $this->findRecentDuplicate($data, $organization);

        if ($existing !== null) {
            return $existing;
        }

        $appointmentRequest = AppointmentRequest::query()->create([
            'organization_id' => $organization?->id,
            'unit_id' => $unit?->id,
            'service_id' => $data['service_id'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'preferred_period' => $data['preferred_period'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'utm_data' => array_filter($data['utm'] ?? []) ?: null,
            'status' => AppointmentRequestStatus::Pending,
            'terms_accepted_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::Created,
            auditable: $appointmentRequest,
            after: [
                'name' => $appointmentRequest->name,
                'phone' => $appointmentRequest->phone,
                'service_id' => $appointmentRequest->service_id,
                'status' => $appointmentRequest->status->value,
            ],
            organization: $organization,
            unit: $unit,
        );

        $this->notifyOwners($appointmentRequest, $organization);

        return $appointmentRequest;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findRecentDuplicate(array $data, ?Organization $organization): ?AppointmentRequest
    {
        $query = AppointmentRequest::query()
            ->when($organization, fn (Builder $q) => $q->where('organization_id', $organization->id))
            ->where('phone', $data['phone'])
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES));

        $query = $this->matchNullable($query, 'service_id', $data['service_id'] ?? null);
        $query = $this->matchNullable($query, 'preferred_date', $data['preferred_date'] ?? null);
        $query = $this->matchNullable($query, 'preferred_period', $data['preferred_period'] ?? null);

        return $query->latest()->first();
    }

    /**
     * @param  Builder<AppointmentRequest>  $query
     * @return Builder<AppointmentRequest>
     */
    private function matchNullable(Builder $query, string $column, mixed $value): Builder
    {
        return $value === null ? $query->whereNull($column) : $query->where($column, $value);
    }

    private function notifyOwners(AppointmentRequest $appointmentRequest, ?Organization $organization): void
    {
        if ($organization === null) {
            return;
        }

        try {
            $organization->memberships()
                ->where('is_owner', true)
                ->where('status', OrganizationMembershipStatus::Active)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->each(fn ($owner) => $owner->notify(new NewAppointmentRequestNotification($appointmentRequest)));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
