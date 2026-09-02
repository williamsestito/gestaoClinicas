<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Garante que a página pública inicial ("/") sempre tenha algum conteúdo,
 * mesmo antes de um administrador configurá-lo pelo Filament. Idempotente:
 * usa firstOrCreate, então nunca sobrescreve edições já feitas no painel.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::query()->firstOrCreate([], [
            'title' => 'Gestão de Clínicas',
            'description' => 'Esta aplicação está em desenvolvimento. A fundação técnica está ativa; os módulos de negócio serão adicionados nas próximas fases.',
            'primary_color' => '#0F766E',
            'secondary_color' => '#F59E0B',
        ]);
    }
}
