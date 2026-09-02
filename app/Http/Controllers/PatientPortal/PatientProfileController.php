<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Actions\Organization\DestroyPatientPhotoAction;
use App\Actions\Organization\UpdatePatientPhotoAction;
use App\Actions\PatientPortal\UpdatePatientPortalProfileAction;
use App\Data\Organization\AddressData;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\UpdatePatientPortalPhotoRequest;
use App\Http\Requests\PatientPortal\UpdatePatientPortalProfileRequest;
use App\Models\PatientUser;
use App\Support\Documents\BrazilianState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Edição do próprio cadastro (ou de um dependente) pelo portal. Nunca usa
 * route-model-binding direto de Patient — o guard autenticado aqui é
 * "patient", não o default ("web") que o binding implícito resolveria; um
 * ID de outro paciente precisa dar 404, nunca vazar a existência do
 * registro (ver docs/modules/patient-portal.md, seção de autorização).
 */
class PatientProfileController extends Controller
{
    public function edit(Request $request, string $patient): Response
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        $found = $patientUser->patients()->with('address')->findOrFail($patient);

        return Inertia::render('patient-portal/patients/Edit', [
            'patient' => [
                'id' => $found->id,
                'name' => $found->name,
                'preferred_name' => $found->preferred_name,
                'document' => $found->document,
                'birth_date' => $found->birth_date->toDateString(),
                'phone' => $found->phone,
                'whatsapp' => $found->whatsapp,
                'email' => $found->email,
                'photo_url' => $found->photo_path ? route('patient-portal.patients.photo.show', $found) : null,
            ],
            'address' => $found->address ? [
                'postal_code' => $found->address->postal_code,
                'street' => $found->address->street,
                'number' => $found->address->number,
                'complement' => $found->address->complement,
                'neighborhood' => $found->address->neighborhood,
                'city' => $found->address->city,
                'state' => $found->address->state,
            ] : null,
            'states' => BrazilianState::codes(),
        ]);
    }

    public function update(
        UpdatePatientPortalProfileRequest $request,
        string $patient,
        UpdatePatientPortalProfileAction $action,
    ): RedirectResponse {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        $found = $patientUser->patients()->findOrFail($patient);

        $validated = $request->validated();

        $action->handle(
            $found,
            collect($validated)->except(['address'])->all(),
            $this->resolveAddressData($validated['address'] ?? null),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dados atualizados com sucesso.']);

        return to_route('patient-portal.dashboard');
    }

    public function updatePhoto(
        UpdatePatientPortalPhotoRequest $request,
        string $patient,
        UpdatePatientPhotoAction $action,
    ): RedirectResponse {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        $found = $patientUser->patients()->findOrFail($patient);

        $action->handle($found, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto atualizada com sucesso.']);

        return back();
    }

    public function destroyPhoto(Request $request, string $patient, DestroyPatientPhotoAction $action): RedirectResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        $found = $patientUser->patients()->findOrFail($patient);

        $action->handle($found);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida com sucesso.']);

        return back();
    }

    /**
     * Serve a foto do disco privado `local` — nunca um link público direto
     * (mesmo racional de PatientController::showPhoto() no admin, só que
     * escopado pelo vínculo da conta do portal em vez de uma Policy).
     */
    public function showPhoto(Request $request, string $patient): StreamedResponse
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        $found = $patientUser->patients()->findOrFail($patient);

        if (! $found->photo_path || ! Storage::disk('local')->exists($found->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($found->photo_path);
    }

    /** @param array<string, mixed>|null $address */
    private function resolveAddressData(?array $address): ?AddressData
    {
        if ($address === null || array_filter($address) === []) {
            return null;
        }

        return AddressData::fromArray(array_merge([
            'postal_code' => '',
            'complement' => null,
        ], $address));
    }
}
