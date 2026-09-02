<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CancelInvitationAction;
use App\Actions\Organization\ResendInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function cancel(Invitation $invitation, CancelInvitationAction $action): RedirectResponse
    {
        $this->authorize('cancel', $invitation);

        $action->handle($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Convite cancelado.']);

        return back();
    }

    public function resend(Invitation $invitation, ResendInvitationAction $action): RedirectResponse
    {
        $this->authorize('resend', $invitation);

        $action->handle($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Convite reenviado com sucesso.']);

        return back();
    }
}
