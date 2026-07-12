<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Job puramente tecnico, sem regra de negocio. Usado apenas por
 * `php artisan app:test-queue` e por diagnostico de infraestrutura, para
 * confirmar que um job despachado via Redis e efetivamente processado por
 * um worker (`queue:work`).
 */
class InfrastructureSmokeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $cacheKey) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, [
            'processed_at' => now()->toIso8601String(),
        ], now()->addMinutes(5));
    }
}
