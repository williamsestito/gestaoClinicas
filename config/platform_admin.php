<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Seeder opcional de administrador da plataforma
    |--------------------------------------------------------------------------
    |
    | Usado exclusivamente por database/seeders/PlatformAdminSeeder.php.
    | Nunca defina uma senha padrao aqui - o seeder e bloqueado quando
    | qualquer uma destas variaveis estiver ausente, e sempre bloqueado em
    | producao.
    |
    */

    'seed' => env('SEED_PLATFORM_ADMIN', false),

    'name' => env('PLATFORM_ADMIN_NAME'),

    'email' => env('PLATFORM_ADMIN_EMAIL'),

    'password' => env('PLATFORM_ADMIN_PASSWORD'),

];
