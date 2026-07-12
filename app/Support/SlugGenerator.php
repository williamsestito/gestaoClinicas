<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Gera um slug único, incrementando um sufixo numérico até não colidir com
 * o resultado de $queryFactory (que deve retornar uma query já escopada,
 * ex.: por organização, quando o slug precisa ser único apenas dentro dela).
 */
final class SlugGenerator
{
    /**
     * @param  callable(string): Builder<*>  $queryFactory
     */
    public static function unique(string $source, callable $queryFactory): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $suffix = 1;

        while ($queryFactory($slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
