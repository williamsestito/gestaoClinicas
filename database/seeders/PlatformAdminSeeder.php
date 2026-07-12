<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder opcional de desenvolvimento. Só cria um administrador da
 * plataforma quando SEED_PLATFORM_ADMIN=true e todas as variáveis
 * PLATFORM_ADMIN_* estiverem definidas — nunca usa senha padrão e é
 * bloqueado em produção. Prefira `php artisan app:create-platform-admin`.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('PlatformAdminSeeder bloqueado em produção.');

            return;
        }

        if (! filter_var(config('platform_admin.seed', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $name = config('platform_admin.name');
        $email = config('platform_admin.email');
        $password = config('platform_admin.password');

        if (blank($name) || blank($email) || blank($password)) {
            $this->command->warn(
                'SEED_PLATFORM_ADMIN=true mas PLATFORM_ADMIN_NAME/EMAIL/PASSWORD não estão todos definidos. Nenhum administrador foi criado.',
            );

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make((string) $password),
                'is_active' => true,
                'is_platform_admin' => true,
                'email_verified_at' => Carbon::now(),
            ],
        );

        $this->command->info("Administrador da plataforma {$email} disponível (via seeder de desenvolvimento).");
    }
}
