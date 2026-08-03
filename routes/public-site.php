<?php

use App\Http\Controllers\PublicAppointmentRequestController;
use App\Http\Controllers\PublicAvailabilityController;
use App\Http\Controllers\PublicGalleryController;
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

// Página "Ver todas" da galeria pública (botão na landing).
Route::get('galeria', [PublicGalleryController::class, 'index'])->name('gallery.index');

// Formulario de solicitacao de agendamento (lead) — throttle curto evita
// envios duplicados/spam sem exigir autenticacao.
Route::post('agendamento', [PublicAppointmentRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('appointment-requests.store');

// Busca pública de disponibilidade — somente leitura, sem autenticação,
// nunca cria reserva. Throttle mais generoso que o de leads porque o
// usuário navega interativamente por vários passos (unidade, especialidade,
// serviço, profissional, data, horário).
Route::prefix('disponibilidade')->name('public-availability.')->middleware('throttle:60,1')->group(function () {
    Route::get('unidades', [PublicAvailabilityController::class, 'units'])->name('units');
    Route::get('especialidades', [PublicAvailabilityController::class, 'specialties'])->name('specialties');
    Route::get('servicos', [PublicAvailabilityController::class, 'services'])->name('services');
    Route::get('profissionais', [PublicAvailabilityController::class, 'professionals'])->name('professionals');
    Route::get('datas', [PublicAvailabilityController::class, 'dates'])->name('dates');
    Route::get('horarios', [PublicAvailabilityController::class, 'times'])->name('times');
});

// robots.txt e sitemap.xml sao gerados dinamicamente (nunca arquivos
// estaticos) para respeitar o ambiente e a politica de indexacao
// configurada — ver App\Http\Controllers\SeoController.
Route::get('robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
