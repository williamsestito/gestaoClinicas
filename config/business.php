<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fuso horario padrao do negocio
    |--------------------------------------------------------------------------
    |
    | Todos os dados temporais sao armazenados em UTC (ver APP_TIMEZONE).
    | Este valor e usado apenas para apresentacao, ate que cada organizacao/
    | unidade possa definir seu proprio fuso horario em fases futuras.
    |
    */

    'default_timezone' => env(
        'BUSINESS_DEFAULT_TIMEZONE',
        'America/Sao_Paulo',
    ),

    /*
    |--------------------------------------------------------------------------
    | Moeda padrao
    |--------------------------------------------------------------------------
    */

    'default_currency' => env(
        'BUSINESS_DEFAULT_CURRENCY',
        'BRL',
    ),

    /*
    |--------------------------------------------------------------------------
    | Locale padrao de negocio
    |--------------------------------------------------------------------------
    */

    'default_locale' => env(
        'BUSINESS_DEFAULT_LOCALE',
        'pt_BR',
    ),

];
