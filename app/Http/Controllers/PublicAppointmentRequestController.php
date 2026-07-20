<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentRequestStatus;
use App\Http\Requests\StoreAppointmentRequestRequest;
use App\Models\AppointmentRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Recebe solicitações de agendamento enviadas pela landing pública. Cria
 * apenas um lead (App\Models\AppointmentRequest) — nunca um agendamento
 * confirmado, pois não existe agenda/disponibilidade real ainda.
 */
class PublicAppointmentRequestController extends Controller
{
    public function store(StoreAppointmentRequestRequest $request): RedirectResponse
    {
        $organization = Organization::query()->first();
        $headquarters = $organization?->headquarters()->first();

        AppointmentRequest::query()->create([
            'organization_id' => $organization?->id,
            'unit_id' => $headquarters?->id,
            'service_id' => $request->validated('service_id'),
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'preferred_period' => $request->validated('preferred_period'),
            'notes' => $request->validated('notes'),
            'status' => AppointmentRequestStatus::Pending,
            'terms_accepted_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recebemos sua solicitação! Entraremos em contato em breve.']);

        return back();
    }
}
