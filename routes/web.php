<?php

use App\Http\Controllers\Auth\DirectPasswordResetController;
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
