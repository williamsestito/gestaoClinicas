<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\AcceptInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AcceptInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fluxo público (sem autenticação) de aceite de convite. O convite é
 * localizado pelo hash do token — nunca por ID sequencial adivinhável.
 */
class InvitationAcceptController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->findByToken($token);

        return Inertia::render('auth/AcceptInvitation', [
            'valid' => $invitation !== null && $invitation->isPending(),
            'organizationName' => $invitation?->organization->name,
            'email' => $invitation?->email,
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitationAction $action): RedirectResponse
    {
        $invitation = $this->findByToken($token);

        abort_if($invitation === null, 404);

        $user = $action->handle($invitation, $request->validated('name'), $request->validated('password'));

        Auth::login($user);

        return to_route('dashboard');
    }

    private function findByToken(string $token): ?Invitation
    {
        return Invitation::query()->where('token_hash', hash('sha256', $token))->first();
    }
}
