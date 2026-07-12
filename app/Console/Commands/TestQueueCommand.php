<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\InfrastructureSmokeJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Diagnostico tecnico: despacha um job via fila Redis e aguarda, de forma
 * limitada, a confirmacao de que um worker (`queue:work`) o processou.
 */
#[Signature('app:test-queue {--timeout=15 : Segundos a aguardar pelo processamento}')]
#[Description('Despacha um job de teste na fila e confirma que um worker o processou')]
class TestQueueCommand extends Command
{
    public function handle(): int
    {
        $cacheKey = 'infrastructure-smoke:'.Str::uuid();
        $timeout = (int) $this->option('timeout');

        $this->info('Despachando InfrastructureSmokeJob...');
        InfrastructureSmokeJob::dispatch($cacheKey);

        $waited = 0;
        while ($waited < $timeout) {
            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
                $this->info("Job processado com sucesso em até {$waited}s.");

                return self::SUCCESS;
            }

            sleep(1);
            $waited++;
        }

        $this->error("Nenhum worker processou o job em {$timeout}s. Verifique se o serviço \"queue\" está em execução.");

        return self::FAILURE;
    }
}
