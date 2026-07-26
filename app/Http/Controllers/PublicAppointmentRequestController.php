<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Public\CreateAppointmentRequestAction;
use App\Http\Requests\StoreAppointmentRequestRequest;
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
    /** Abaixo deste tempo entre a renderização do formulário e o envio, tratamos como automatizado. */
    private const MIN_FILL_TIME_MS = 3000;

    public function __construct(private readonly CreateAppointmentRequestAction $action) {}

    public function store(StoreAppointmentRequestRequest $request): RedirectResponse
    {
        // Honeypot preenchido ou envio rápido demais para ser humano: responde
        // como se tivesse dado certo (sem persistir nada), para não revelar a
        // existência da defesa a quem estiver testando o formulário.
        if ($this->looksAutomated($request)) {
            return $this->successResponse();
        }

        $organization = Organization::query()->first();
        $headquarters = $organization?->headquarters()->first();

        $this->action->handle($request->validated(), $organization, $headquarters);

        return $this->successResponse();
    }

    private function looksAutomated(StoreAppointmentRequestRequest $request): bool
    {
        if ($request->filled('website')) {
            return true;
        }

        $renderedAt = $request->integer('form_rendered_at');

        return $renderedAt > 0 && (int) (microtime(true) * 1000) - $renderedAt < self::MIN_FILL_TIME_MS;
    }

    private function successResponse(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitação enviada com sucesso! Nossa equipe entrará em contato para confirmar a disponibilidade do horário.',
        ]);

        return back();
    }
}
