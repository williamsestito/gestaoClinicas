<?php

declare(strict_types=1);

namespace App\Http\Controllers\PatientPortal;

use App\Actions\PatientPortal\RegisterPatientUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientPortal\RegisterPatientUserRequest;
use App\Models\Organization;
use App\Support\Documents\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Autocadastro público do portal do paciente. A organização é resolvida
 * como Organization::query()->first() — instalação single-tenant (ver
 * docs/decisions/ADR-010-single-tenant-install-and-seo.md), mesmo padrão
 * de PublicSiteController/PublicAppointmentRequestController.
 */
class RegisteredPatientUserController extends Controller
{
    /** Abaixo deste tempo entre a renderização do formulário e o envio, tratamos como automatizado. */
    private const MIN_FILL_TIME_MS = 3000;

    /**
     * `prefill`: dados já digitados no formulário público de agendamento
     * (nome/telefone/e-mail/CPF), passados via query string pelo link
     * "Ainda não tenho cadastro" do popup de confirmação (ver
     * LandingSchedulingSection.vue) — evita a pessoa redigitar tudo.
     * Só pré-preenche, nunca valida/confia nesses valores aqui; a
     * validação de verdade continua em store().
     */
    public function create(Request $request): Response
    {
        $organization = Organization::query()->first();

        return Inertia::render('patient-portal/Register', [
            'organizationConfigured' => $organization !== null,
            'prefill' => [
                'name' => $request->string('name')->limit(255, '')->toString() ?: null,
                'phone' => $request->string('phone')->limit(20, '')->toString() ?: null,
                'email' => $request->string('email')->limit(255, '')->toString() ?: null,
                'document' => $request->filled('document')
                    ? Document::onlyDigits($request->string('document')->toString())
                    : null,
            ],
        ]);
    }

    public function store(RegisterPatientUserRequest $request, RegisterPatientUserAction $action): RedirectResponse
    {
        // Responde como se tivesse dado certo (sem persistir nada), para não
        // revelar a existência da defesa a quem estiver testando o
        // formulário — mesmo espírito de PublicAppointmentRequestController.
        if ($this->looksAutomated($request)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Cadastro enviado com sucesso.']);

            return to_route('login');
        }

        $organization = Organization::query()->first();

        if ($organization === null) {
            return back()->withErrors(['email' => 'Cadastro indisponível no momento.']);
        }

        $validated = $request->validated();

        $patientUser = $action->handle(
            $organization,
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ],
            $validated['registering_for'],
            [
                'birth_date' => $validated['birth_date'] ?? null,
                'document' => $validated['document'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ],
            [
                'name' => $validated['dependent_name'] ?? null,
                'birth_date' => $validated['dependent_birth_date'] ?? null,
                'document' => $validated['dependent_document'] ?? null,
                'phone' => $validated['dependent_phone'] ?? null,
                'relationship' => $validated['relationship'] ?? null,
                'responsible_phone' => $validated['responsible_phone'] ?? null,
            ],
            $request->file('photo'),
        );

        $patientUser->sendEmailVerificationNotification();

        Auth::guard('patient')->login($patientUser);
        $request->session()->regenerate();

        return to_route('patient-portal.dashboard');
    }

    /**
     * Honeypot + tempo mínimo de preenchimento — mesma técnica de
     * PublicAppointmentRequestController::looksAutomated(), único padrão
     * anti-bot já existente no projeto.
     */
    private function looksAutomated(RegisterPatientUserRequest $request): bool
    {
        if ($request->filled('website')) {
            return true;
        }

        $renderedAt = $request->integer('form_rendered_at');

        return $renderedAt > 0 && (int) (microtime(true) * 1000) - $renderedAt < self::MIN_FILL_TIME_MS;
    }
}
