<?php

use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Site Routes
|--------------------------------------------------------------------------
|
| Rotas publicas do site institucional. Nesta fase, apenas a pagina inicial,
| cujo conteudo (titulo, descricao, imagem, cores, SEO) e administrado via o
| painel Filament (App\Filament\Pages\ManageSiteContent). A futura pagina
| comercial completa sera implementada em fase propria.
|
*/

Route::get('/', [PublicSiteController::class, 'home'])->name('home');

// robots.txt e sitemap.xml sao gerados dinamicamente (nunca arquivos
// estaticos) para respeitar o ambiente e a politica de indexacao
// configurada — ver App\Http\Controllers\SeoController.
Route::get('robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
