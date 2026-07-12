<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Diagnostico da infraestrutura local. Nao imprime senhas, chaves ou
 * tokens - apenas sucesso/falha por item. Retorna 0 somente quando todos os
 * itens obrigatorios estao corretos; MinIO/S3 e tratado como opcional
 * enquanto FILESYSTEM_DISK=local.
 */
#[Signature('app:doctor')]
#[Description('Verifica a saude da infraestrutura local (banco, cache, fila, storage, mail)')]
class AppDoctor extends Command
{
    /** @var bool indica se algum item obrigatorio falhou */
    private bool $hasRequiredFailure = false;

    public function handle(): int
    {
        $this->info('Diagnóstico da infraestrutura — Gestão de Clínicas');
        $this->newLine();

        $this->checkAppKey();
        $this->checkEnvironment();
        $this->checkDatabase();
        $this->checkRedis();
        $this->checkCache();
        $this->checkQueueConfig();
        $this->checkLocalStorage();
        $this->checkMailConfig();
        $this->checkStoragePermissions();
        $this->checkMinio();

        $this->newLine();

        if ($this->hasRequiredFailure) {
            $this->error('Diagnóstico concluído com falhas em itens obrigatórios.');

            return self::FAILURE;
        }

        $this->info('Diagnóstico concluído: todos os itens obrigatórios estão corretos.');

        return self::SUCCESS;
    }

    private function ok(string $label, string $detail = ''): void
    {
        $this->line("  <fg=green>✓</> {$label}".($detail !== '' ? " <fg=gray>({$detail})</>" : ''));
    }

    private function reportFailure(string $label, string $detail, bool $required = true): void
    {
        $icon = $required ? '<fg=red>✗</>' : '<fg=yellow>!</>';
        $this->line("  {$icon} {$label} <fg=gray>({$detail})</>");

        if ($required) {
            $this->hasRequiredFailure = true;
        }
    }

    private function checkAppKey(): void
    {
        if (filled(config('app.key'))) {
            $this->ok('APP_KEY definida');
        } else {
            $this->reportFailure('APP_KEY definida', 'chave ausente — execute php artisan key:generate');
        }
    }

    private function checkEnvironment(): void
    {
        $this->ok('Ambiente', app()->environment());
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $database = DB::connection()->getDatabaseName();
            $this->ok('Conexão PostgreSQL', "banco: {$database}");
        } catch (Throwable $e) {
            $this->reportFailure('Conexão PostgreSQL', 'não foi possível conectar ao banco de dados');
        }
    }

    private function checkRedis(): void
    {
        try {
            Redis::connection()->ping();
            $this->ok('Conexão Redis');
        } catch (Throwable $e) {
            $this->reportFailure('Conexão Redis', 'não foi possível conectar ao Redis');
        }
    }

    private function checkCache(): void
    {
        $key = 'doctor:cache:'.Str::random(8);

        try {
            Cache::put($key, 'ok', 10);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            if ($ok) {
                $this->ok('Gravação/leitura no cache', config('cache.default'));
            } else {
                $this->reportFailure('Gravação/leitura no cache', 'valor lido difere do gravado');
            }
        } catch (Throwable $e) {
            $this->reportFailure('Gravação/leitura no cache', 'falha ao gravar/ler no driver de cache');
        }
    }

    private function checkQueueConfig(): void
    {
        $connection = config('queue.default');

        if ($connection === 'redis') {
            $this->ok('Configuração da fila', 'connection: redis');
        } else {
            $this->reportFailure('Configuração da fila', "esperado \"redis\", encontrado \"{$connection}\"");
        }
    }

    private function checkLocalStorage(): void
    {
        $path = 'doctor-'.Str::random(8).'.txt';

        try {
            Storage::disk('local')->put($path, 'ok');
            $ok = Storage::disk('local')->get($path) === 'ok';
            Storage::disk('local')->delete($path);

            if ($ok) {
                $this->ok('Gravação/leitura no disco local');
            } else {
                $this->reportFailure('Gravação/leitura no disco local', 'valor lido difere do gravado');
            }
        } catch (Throwable $e) {
            $this->reportFailure('Gravação/leitura no disco local', 'falha ao gravar/ler no disco "local"');
        }
    }

    private function checkMailConfig(): void
    {
        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');

        // Transportes como "array"/"log" nao usam host (usados em testes);
        // exigimos host apenas para transportes de rede (ex.: smtp).
        $requiresHost = $transport === 'smtp';
        $host = config('mail.mailers.'.$mailer.'.host');

        if (filled($mailer) && filled($transport) && (! $requiresHost || filled($host))) {
            $this->ok('Configuração do mailer', "mailer: {$mailer}");
        } else {
            $this->reportFailure('Configuração do mailer', 'mailer ou host não configurados');
        }
    }

    private function checkStoragePermissions(): void
    {
        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        foreach ($paths as $label => $path) {
            if (is_dir($path) && is_writable($path)) {
                $this->ok("Permissões de {$label}");
            } else {
                $this->reportFailure("Permissões de {$label}", "pasta ausente ou sem permissão de escrita: {$path}");
            }
        }
    }

    private function checkMinio(): void
    {
        $required = config('filesystems.default') !== 'local';

        try {
            $disk = Storage::disk('s3');
            $path = 'doctor-'.Str::random(8).'.txt';
            $disk->put($path, 'ok');
            $ok = $disk->get($path) === 'ok';
            $disk->delete($path);

            if ($ok) {
                $this->ok('Conexão com MinIO/S3 (disco "s3")');
            } else {
                $this->reportFailure('Conexão com MinIO/S3 (disco "s3")', 'valor lido difere do gravado', $required);
            }
        } catch (Throwable $e) {
            $this->reportFailure(
                'Conexão com MinIO/S3 (disco "s3")',
                $required ? 'falha ao conectar ao MinIO' : 'opcional — FILESYSTEM_DISK=local',
                $required,
            );
        }
    }
}
