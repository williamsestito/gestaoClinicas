<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Ponto de entrada dos seeders. Não cria nenhum usuário genérico/inseguro
 * — apenas encadeia seeders seguros e condicionais (ver PlatformAdminSeeder).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);
        $this->call(SiteSettingSeeder::class);
    }
}
