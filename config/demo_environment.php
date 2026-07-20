<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente de demonstração local
    |--------------------------------------------------------------------------
    |
    | Usado exclusivamente por database/seeders/DemoOrganizationSeeder.php,
    | bloqueado em produção. Garante que os dois usuários de referência do
    | ambiente local (administrador técnico e administrador da clínica)
    | tenham os vínculos e papéis corretos. Nunca define uma senha padrão
    | aqui — se o usuário "administrador da clínica" ainda não existir, só
    | é criado quando estas variáveis estiverem definidas no .env.
    |
    */

    'clinic_admin_email' => env('DEMO_CLINIC_ADMIN_EMAIL', 'admin@gestao-clinicas.local'),

    'clinic_admin_name' => env('DEMO_CLINIC_ADMIN_NAME', 'Administrador da Clínica'),

    'clinic_admin_password' => env('DEMO_CLINIC_ADMIN_PASSWORD'),

];
