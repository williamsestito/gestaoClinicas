<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\AddMedicalRecordAddendumAction;
use App\Actions\Organization\CreateMedicalRecordAction;
use App\Actions\Organization\FinalizeMedicalRecordAction;
use App\Actions\Organization\ReleaseMedicalRecordToPatientAction;
use App\Actions\Organization\UpdateMedicalRecordDraftAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AddMedicalRecordAddendumRequest;
use App\Http\Requests\Organization\FinalizeMedicalRecordRequest;
use App\Http\Requests\Organization\ReleaseMedicalRecordRequest;
use App\Http\Requests\Organization\UpdateMedicalRecordDraftRequest;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub do prontuário de um atendimento (Etapa 4 do roadmap). Autorização
 * sempre via App\Policies\MedicalRecordPolicy — nunca aceita
 * organization_id/professional_id do frontend.
 */
class MedicalRecordController extends Controller
{
    public function show(Appointment $appointment, CreateMedicalRecordAction $action): Response
    {
        $medicalRecord = MedicalRecord::query()->where('appointment_id', $appointment->id)->first();

        if ($medicalRecord === null) {
            $this->authorize('create', [MedicalRecord::class, $appointment]);
            $medicalRecord = $action->handle($appointment);
        } else {
            $this->authorize('view', $medicalRecord);
        }

        $medicalRecord->load(['patient:id,name,preferred_name', 'professional:id,display_name', 'addenda.professional:id,display_name', 'files.uploadedBy:id,name']);

        /** @var User $user */
        $user = Auth::user();
        $canEdit = $user->can('update', $medicalRecord);

        return Inertia::render('settings/medical-records/Show', [
            'appointment' => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
            ],
            'medicalRecord' => $this->presentRecord($medicalRecord),
            'canEdit' => $canEdit,
            'canFinalize' => $user->can('finalize', $medicalRecord),
            'canRelease' => $user->can('release', $medicalRecord),
            'canAddAddendum' => $user->can('addAddendum', $medicalRecord),
        ]);
    }

    public function update(UpdateMedicalRecordDraftRequest $request, MedicalRecord $medicalRecord, UpdateMedicalRecordDraftAction $action): RedirectResponse
    {
        $action->handle($medicalRecord, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Prontuário salvo.']);

        return back();
    }

    public function finalize(FinalizeMedicalRecordRequest $request, MedicalRecord $medicalRecord, FinalizeMedicalRecordAction $action): RedirectResponse
    {
        $action->handle($medicalRecord);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Prontuário finalizado.']);

        return back();
    }

    public function release(ReleaseMedicalRecordRequest $request, MedicalRecord $medicalRecord, ReleaseMedicalRecordToPatientAction $action): RedirectResponse
    {
        $action->handle($medicalRecord);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Prontuário liberado para o paciente.']);

        return back();
    }

    public function addAddendum(AddMedicalRecordAddendumRequest $request, MedicalRecord $medicalRecord, AddMedicalRecordAddendumAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $author = $user->professionals()
            ->where('organization_id', $medicalRecord->organization_id)
            ->where('status', RecordStatus::Active)
            ->firstOrFail();

        $action->handle($medicalRecord, $author, $request->validated('body'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Adendo adicionado.']);

        return back();
    }

    /**
     * Histórico de prontuários de um paciente — reaproveitado por "Meus
     * pacientes" (ver App\Http\Controllers\Organization\MyPatientsController,
     * não alterado por esta rota).
     */
    public function patientHistory(Patient $patient): Response
    {
        $this->authorize('viewPatientHistory', $patient);

        $records = MedicalRecord::query()
            ->where('organization_id', $patient->organization_id)
            ->where('patient_id', $patient->id)
            ->with(['professional:id,display_name', 'appointment:id,starts_at'])
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MedicalRecord $medicalRecord) => [
                'id' => $medicalRecord->id,
                'appointment_id' => $medicalRecord->appointment_id,
                'status' => $medicalRecord->status->value,
                'status_label' => $medicalRecord->status->label(),
                'professional_name' => $medicalRecord->professional?->display_name,
                'appointment_starts_at' => $medicalRecord->appointment?->starts_at?->toIso8601String(),
                'finalized_at' => $medicalRecord->finalized_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/my-patients/MedicalRecordHistory', [
            'patient' => ['id' => $patient->id, 'name' => $patient->preferred_name ?: $patient->name],
            'records' => $records,
        ]);
    }

    /** @return array<string, mixed> */
    private function presentRecord(MedicalRecord $medicalRecord): array
    {
        return [
            'id' => $medicalRecord->id,
            'status' => $medicalRecord->status->value,
            'status_label' => $medicalRecord->status->label(),
            'patient_name' => $medicalRecord->patient?->preferred_name ?: $medicalRecord->patient?->name,
            'professional_name' => $medicalRecord->professional?->display_name,
            'anamnesis' => $medicalRecord->anamnesis,
            'preexisting_conditions' => $medicalRecord->preexisting_conditions,
            'allergies' => $medicalRecord->allergies,
            'current_medications' => $medicalRecord->current_medications,
            'contraindications' => $medicalRecord->contraindications,
            'evaluation' => $medicalRecord->evaluation,
            'treatment_plan' => $medicalRecord->treatment_plan,
            'procedures_performed' => $medicalRecord->procedures_performed,
            'evolution_notes' => $medicalRecord->evolution_notes,
            'prescriptions' => $medicalRecord->prescriptions,
            'referrals' => $medicalRecord->referrals,
            'specialty_data' => $medicalRecord->specialty_data,
            'has_return_right' => $medicalRecord->has_return_right,
            'return_window_days' => $medicalRecord->return_window_days,
            'finalized_at' => $medicalRecord->finalized_at?->toIso8601String(),
            'released_to_patient_at' => $medicalRecord->released_to_patient_at?->toIso8601String(),
            'addenda' => $medicalRecord->addenda->map(fn ($addendum) => [
                'id' => $addendum->id,
                'body' => $addendum->body,
                'author_name' => $addendum->professional?->display_name,
                'created_at' => $addendum->created_at->toIso8601String(),
            ]),
            'files' => $medicalRecord->files->map(fn ($file) => [
                'id' => $file->id,
                'category' => $file->category->value,
                'category_label' => $file->category->label(),
                'original_filename' => $file->original_filename,
                'uploaded_by_name' => $file->uploadedBy?->name,
                'created_at' => $file->created_at->toIso8601String(),
            ]),
        ];
    }
}
