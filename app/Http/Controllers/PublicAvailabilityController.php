<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublicAvailabilityDatesRequest;
use App\Http\Requests\PublicAvailabilityProfessionalsRequest;
use App\Http\Requests\PublicAvailabilityServicesRequest;
use App\Http\Requests\PublicAvailabilitySpecialtiesRequest;
use App\Http\Requests\PublicAvailabilityTimesRequest;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Services\Availability\PublicAvailabilityFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Endpoints somente leitura da busca pública de disponibilidade — sem
 * autenticação, protegidos por rate limiting (ver routes/public-site.php).
 * Nunca retornam Models completos, observações internas, documentos,
 * jornada bruta ou bloqueios brutos — apenas os agregados mínimos que
 * App\Services\Availability\PublicAvailabilityFinder já normaliza. Nunca
 * criam, reservam ou prometem confirmação de horário (não existe agenda
 * transacional nesta fase).
 */
class PublicAvailabilityController extends Controller
{
    public function __construct(private readonly PublicAvailabilityFinder $finder) {}

    public function units(): JsonResponse
    {
        $organization = $this->publishedOrganization();

        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->finder->eligibleUnits($organization)->all()]);
    }

    public function specialties(PublicAvailabilitySpecialtiesRequest $request): JsonResponse
    {
        $organization = $this->publishedOrganization();

        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->finder->eligibleSpecialties($organization, $request->string('unit_id')->toString())->all()]);
    }

    public function services(PublicAvailabilityServicesRequest $request): JsonResponse
    {
        $organization = $this->publishedOrganization();

        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->finder->eligibleServices(
            $organization,
            $request->string('unit_id')->toString(),
            $request->string('specialty_id')->toString() ?: null,
        )->all()]);
    }

    public function professionals(PublicAvailabilityProfessionalsRequest $request): JsonResponse
    {
        $organization = $this->publishedOrganization();

        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->finder->eligibleProfessionals(
            $organization,
            $request->string('unit_id')->toString(),
            $request->string('service_id')->toString(),
            $request->string('specialty_id')->toString() ?: null,
        )->all()]);
    }

    public function dates(PublicAvailabilityDatesRequest $request): JsonResponse
    {
        $organization = $this->publishedOrganization();

        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        $month = Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth();

        return response()->json(['data' => $this->finder->availableDates(
            $organization,
            $request->string('unit_id')->toString(),
            $request->string('service_id')->toString(),
            $request->string('professional_id')->toString() ?: null,
            $request->string('specialty_id')->toString() ?: null,
            $month,
        )->all()]);
    }

    public function times(PublicAvailabilityTimesRequest $request): JsonResponse
    {
        $organization = $this->publishedOrganization();

        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->string('date')->toString())->startOfDay();

        return response()->json(['data' => $this->finder->availableTimes(
            $organization,
            $request->string('unit_id')->toString(),
            $request->string('service_id')->toString(),
            $request->string('professional_id')->toString() ?: null,
            $request->string('specialty_id')->toString() ?: null,
            $date,
        )->all()]);
    }

    /**
     * Mesma regra de gate do restante do site público: só responde com
     * dados reais quando existe organização configurada e o site está
     * publicado — nunca vaza disponibilidade de uma instalação ainda em
     * configuração ou com o site despublicado.
     */
    private function publishedOrganization(): ?Organization
    {
        $organization = Organization::query()->first();
        $siteSetting = SiteSetting::query()->first();

        if ($organization === null || $siteSetting === null || ! $siteSetting->is_published) {
            return null;
        }

        return $organization;
    }
}
