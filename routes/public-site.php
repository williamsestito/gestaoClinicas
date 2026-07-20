<?php

use App\Http\Controllers\PublicAppointmentRequestController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Site Routes
|--------------------------------------------------------------------------
|
| Rotas publicas do site institucional (landing page), cujo conteudo e
| administrado inteiramente pelas paginas Vue em resources/js/pages/settings/site
| (secoes, beneficios, servicos, equipe, galeria, depoimentos, FAQ, SEO).
|
*/

Route::get('/', [PublicSiteController::class, 'home'])->name('home');

// Formulario de solicitacao de agendamento (lead) — throttle curto evita
// envios duplicados/spam sem exigir autenticacao.
Route::post('agendamento', [PublicAppointmentRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('appointment-requests.store');

// robots.txt e sitemap.xml sao gerados dinamicamente (nunca arquivos
// estaticos) para respeitar o ambiente e a politica de indexacao
// configurada — ver App\Http\Controllers\SeoController.
Route::get('robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
