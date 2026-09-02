<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Enums\AppointmentStatus;
use App\Enums\PatientUserLinkRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PatientUser;
use App\Models\PatientUserLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PatientPortalDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        // whereHas('patient'): um Patient soft-deletado pela recepção
        // (DeletePatientAction não bloqueia isso, nem desfaz o vínculo)
        // faria $link->patient resolver null e o acesso a ->id explodir —
        // achado de security-review desta etapa.
        $links = $patientUser->links()->whereHas('patient')->with('patient.preferredUnit')->get();

        $patients = $links->map(fn (PatientUserLink $link) => $this->summarize($link));

        // "Meus dados" no menu vai direto para o próprio cadastro (papel
        // self) quando existir — sem isso, uma conta que só gerencia a si
        // mesma ainda precisava passar pela lista antes de editar.
        $ownPatientId = $links->firstWhere('role', PatientUserLinkRole::Self)?->patient?->id;

        // Telefone/WhatsApp da unidade de preferência do primeiro paciente
        // vinculado (ou a sede da organização, na ausência de preferência)
        // — mesmo contato para toda a conta, não um "fale conosco" por
        // paciente.
        $contactUnit = $links->first()?->patient->preferredUnit
            ?? $patientUser->organization->headquarters()->first();

        return Inertia::render('patient-portal/Dashboard', [
            'patients' => $patients,
            'ownPatientId' => $ownPatientId,
            'clinicContact' => $contactUnit ? [
                'name' => $contactUnit->name,
                'phone' => $contactUnit->phone,
                'whatsapp' => $contactUnit->whatsapp,
            ] : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function summarize(PatientUserLink $link): array
    {
        $patient = $link->patient;

        $nextAppointment = $patient->appointments()
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Completed])
            ->where('starts_at', '>=', Carbon::now())
            ->orderBy('starts_at')
            ->first();

        $lastAppointment = $patient->appointments()
            ->where('status', AppointmentStatus::Completed)
            ->orderByDesc('starts_at')
            ->first();

        $pendingRequestsCount = $patient->appointmentRequests()->whereNull('appointment_id')->count();

        return [
            'id' => $patient->id,
            'name' => $patient->preferred_name ?: $patient->name,
            'birth_date' => $patient->birth_date->toDateString(),
            'role' => $link->role->value,
            'role_label' => $link->role->label(),
            'photo_url' => $patient->photo_path
                ? route('patient-portal.patients.photo.show', $patient)
                : null,
            'next_appointment' => $this->formatAppointment($nextAppointment),
            'last_appointment' => $this->formatAppointment($lastAppointment),
            'pending_requests_count' => $pendingRequestsCount,
        ];
    }

    /** @return array<string, string>|null */
    private function formatAppointment(?Appointment $appointment): ?array
    {
        if ($appointment === null) {
            return null;
        }

        return [
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'status_label' => $appointment->status->label(),
        ];
    }
}
