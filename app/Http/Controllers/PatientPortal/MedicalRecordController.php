<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Enums\MedicalRecordStatus;
use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\PatientUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prontuários finalizados e liberados de um paciente (RN-014: "o paciente
 * só visualiza registros clínicos finalizados e liberados"). Mesmo padrão
 * anti-IDOR do resto do portal (ver PatientAppointmentController): nunca
 * usa Policy nem route-model-binding direto — o paciente é sempre
 * resolvido via `PatientUser::patients()->findOrFail()`.
 */
class MedicalRecordController extends Controller
{
    public function index(Request $request, string $patient): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');
        $found = $patientUser->patients()->findOrFail($patient);

        $records = $found->medicalRecords()
            ->where('status', MedicalRecordStatus::Finalized)
            ->whereNotNull('released_to_patient_at')
            ->with(['professional:id,display_name', 'appointment:id,starts_at', 'addenda', 'files'])
            ->orderByDesc('finalized_at')
            ->get()
            ->map(fn (MedicalRecord $medicalRecord) => [
                'id' => $medicalRecord->id,
                'appointment_starts_at' => $medicalRecord->appointment?->starts_at?->toIso8601String(),
                'professional_name' => $medicalRecord->professional?->display_name,
                'anamnesis' => $medicalRecord->anamnesis,
                'evaluation' => $medicalRecord->evaluation,
                'treatment_plan' => $medicalRecord->treatment_plan,
                'evolution_notes' => $medicalRecord->evolution_notes,
                'prescriptions' => $medicalRecord->prescriptions,
                'referrals' => $medicalRecord->referrals,
                'has_return_right' => $medicalRecord->has_return_right,
                'return_window_days' => $medicalRecord->return_window_days,
                'finalized_at' => $medicalRecord->finalized_at?->toIso8601String(),
                'addenda' => $medicalRecord->addenda->map(fn ($addendum) => [
                    'id' => $addendum->id,
                    'body' => $addendum->body,
                    'created_at' => $addendum->created_at->toIso8601String(),
                ])->values()->all(),
                'files' => $medicalRecord->files->map(fn ($file) => [
                    'id' => $file->id,
                    'category_label' => $file->category->label(),
                    'original_filename' => $file->original_filename,
                ])->values()->all(),
            ]);

        return Inertia::render('patient-portal/medical-records/Index', [
            'patient' => ['id' => $found->id, 'name' => $found->preferred_name ?: $found->name],
            'records' => $records,
        ]);
    }
}
