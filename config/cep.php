<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ordem dos provedores de consulta de CEP
    |--------------------------------------------------------------------------
    |
    | A consulta tenta cada provedor nesta ordem, parando no primeiro que
    | retornar um endereço válido. Este é o único local que define essa
    | ordem — não duplique esta lista em outro arquivo.
    |
    */

    'providers' => [
        'awesomeapi',
        'apicep',
        'viacep',
    ],

    'timeout_seconds' => 3,

    'cache_ttl_days' => 30,

];
