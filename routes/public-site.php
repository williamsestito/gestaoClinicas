<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Site Routes
|--------------------------------------------------------------------------
|
| Rotas publicas do site institucional. Nesta fase, apenas a pagina inicial,
| que informa que a aplicacao esta em desenvolvimento e da acesso a
| login/cadastro. A futura pagina comercial sera implementada em fase propria.
|
*/

Route::inertia('/', 'Welcome')->name('home');
