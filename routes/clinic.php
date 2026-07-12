<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Clinic Routes
|--------------------------------------------------------------------------
|
| Rotas do contexto autenticado da clinica (equipe/profissionais). Nesta
| fase existe apenas o dashboard tecnico. Modulos de negocio (agenda,
| pacientes, prontuario, etc.) serao adicionados aqui em fases futuras.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard', [
        'appEnvironment' => app()->environment(),
    ]))->name('dashboard');
});
