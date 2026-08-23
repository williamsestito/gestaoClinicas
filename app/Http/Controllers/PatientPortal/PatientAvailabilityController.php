<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\PatientAvailabilityDatesRequest;
use App\Http\Requests\PatientPortal\PatientAvailabilityProfessionalsRequest;
use App\Http\Requests\PatientPortal\PatientAvailabilityServicesRequest;
use App\Http\Requests\PatientPortal\PatientAvailabilitySpecialtiesRequest;
use App\Http\Requests\PatientPortal\PatientAvailabilityTimesRequest;
use App\Models\Organization;
use App\Models\PatientUser;
use App\Services\Availability\PublicAvailabilityFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mesma cadeia unidade → especialidade → serviço → profissional → datas →
 * horários da busca pública da landing (App\Http\Controllers\PublicAvailabilityController),
 * reaproveitando o mesmo serviço somente leitura — mas escopada pela
 * organização do paciente autenticado (nunca Organization::first(), nunca
 * condicionada a SiteSetting::is_published: o portal do paciente não
 * depende do site público estar publicado) e incluindo serviços não
 * marcados como vitrine pública (ver PublicAvailabilityFinder::eligibleServices()).
 */
class PatientAvailabilityController extends Controller
{
    public function __construct(private readonly PublicAvailabilityFinder $finder) {}

    public function units(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->finder->eligibleUnits($this->organization($request))->all()]);
    }

    public function specialties(PatientAvailabilitySpecialtiesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->finder->eligibleSpecialties(
            $this->organization($request),
            $request->string('unit_id')->toString(),
        )->all()]);
    }

    public function services(PatientAvailabilityServicesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->finder->eligibleServices(
            $this->organization($request),
            $request->string('unit_id')->toString(),
            $request->string('specialty_id')->toString() ?: null,
            includeNonPublic: true,
        )->all()]);
    }

    public function professionals(PatientAvailabilityProfessionalsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->finder->eligibleProfessionals(
            $this->organization($request),
            $request->string('unit_id')->toString(),
            $request->string('service_id')->toString(),
            $request->string('specialty_id')->toString() ?: null,
        )->all()]);
    }

    public function dates(PatientAvailabilityDatesRequest $request): JsonResponse
    {
        $month = Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth();

        return response()->json(['data' => $this->finder->availableDates(
            $this->organization($request),
            $request->string('unit_id')->toString(),
            $request->string('service_id')->toString(),
            $request->string('professional_id')->toString() ?: null,
            $request->string('specialty_id')->toString() ?: null,
            $month,
        )->all()]);
    }

    public function times(PatientAvailabilityTimesRequest $request): JsonResponse
    {
        $date = Carbon::createFromFormat('Y-m-d', $request->string('date')->toString())->startOfDay();

        return response()->json(['data' => $this->finder->availableTimes(
            $this->organization($request),
            $request->string('unit_id')->toString(),
            $request->string('service_id')->toString(),
            $request->string('professional_id')->toString() ?: null,
            $request->string('specialty_id')->toString() ?: null,
            $date,
        )->all()]);
    }

    private function organization(Request $request): Organization
    {
        /** @var PatientUser $patientUser */
        $patientUser = $request->user('patient');

        return Organization::query()->findOrFail($patientUser->organization_id);
    }
}
