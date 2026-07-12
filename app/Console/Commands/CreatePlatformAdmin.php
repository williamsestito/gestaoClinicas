<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria (ou promove) com segurança um administrador da plataforma. Nunca
 * aceita senha por argumento de linha de comando nem possui credenciais
 * fixas — tudo é solicitado interativamente e nada é registrado em log.
 */
#[Signature('app:create-platform-admin')]
#[Description('Cria ou promove, de forma segura, um administrador da plataforma')]
class CreatePlatformAdmin extends Command
{
    public function handle(): int
    {
        $this->info('Criação de administrador da plataforma');

        $name = (string) $this->ask('Nome completo');
        $email = (string) $this->ask('E-mail');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser && ! $this->confirm(
            "Já existe um usuário com o e-mail {$email}. Deseja promovê-lo a administrador da plataforma?",
            false,
        )) {
            $this->warn('Operação cancelada.');

            return self::FAILURE;
        }

        $password = $this->secret('Senha (mínimo 12 caracteres, com letras, números e símbolos)');
        $passwordConfirmation = $this->secret('Confirme a senha');

        if ($password !== $passwordConfirmation) {
            $this->error('As senhas não coincidem.');

            return self::FAILURE;
        }

        $passwordValidator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $existingUser ?? new User(['email' => $email]);
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->is_active = true;
        $user->is_platform_admin = true;
        $user->email_verified_at ??= Carbon::now();
        $user->save();

        $this->info(
            $existingUser
                ? "Usuário {$email} promovido a administrador da plataforma."
                : "Administrador da plataforma {$email} criado com sucesso.",
        );

        return self::SUCCESS;
    }
}
