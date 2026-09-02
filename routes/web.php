<?php

use App\Http\Controllers\Auth\DirectPasswordResetController;
use App\Http\Controllers\Auth\SendPasswordResetLinkController;
use App\Http\Controllers\Organization\InvitationAcceptController;
use App\Http\Controllers\PostalCodeLookupController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/public-site.php';
require __DIR__.'/clinic.php';
require __DIR__.'/patient-portal.php';
require __DIR__.'/platform.php';
require __DIR__.'/settings.php';

// Atalho de redefinição de senha exclusivo de local/testing (ver
// DirectPasswordResetController) — complementa o fluxo padrão do Fortify
// por link de e-mail, que continua intacto em produção.
Route::middleware('throttle:direct-password-reset')->group(function () {
    Route::post('forgot-password/confirm-email', [DirectPasswordResetController::class, 'confirmEmail'])
        ->name('password.direct-confirm-email');

    Route::post('forgot-password/direct-reset', [DirectPasswordResetController::class, 'update'])
        ->name('password.direct-reset');
});

// "Esqueci minha senha" unificado em /login (ver SendPasswordResetLinkController)
// — substitui o POST nativo do Fortify (password.email, que só olha a
// tabela users) como destino do formulário em auth/ForgotPassword.vue. A
// rota do Fortify continua registrada mas não é mais usada por nenhuma
// tela.
Route::post('esqueci-senha', [SendPasswordResetLinkController::class, 'store'])
    ->middleware('throttle:forgot-password-link')
    ->name('forgot-password.send');

// Endpoint interno (JSON) usado pelo formulário de endereço para preencher
// rua/bairro/cidade/UF a partir do CEP. Não é uma API pública — exige
// alguém autenticado, mas em qualquer um dos dois guards (staff e portal
// do paciente usam o mesmo AddressFields.vue/useCepLookup()). Vive aqui
// (não em clinic.php/patient-portal.php) porque não tem contexto de
// organização/unidade nem de paciente — é uma consulta externa cacheada,
// igual para qualquer um dos dois lados.
Route::middleware(['auth:web,patient', 'throttle:30,1'])->group(function () {
    Route::get('cep/{postalCode}', PostalCodeLookupController::class)
        ->where('postalCode', '[0-9\-]{8,9}')
        ->name('postal-code.lookup');
});

// Aceite de convite: fluxo público (sem autenticação), localizado pelo
// hash do token — ver InvitationAcceptController.
Route::middleware('throttle:6,1')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptController::class, 'show'])
        ->name('invitations.accept');

    Route::post('invitations/{token}', [InvitationAcceptController::class, 'store'])
        ->name('invitations.store');
});
