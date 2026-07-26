<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * As regras `image`/`mimes` só checam o MIME/extensão declarados pelo
 * cliente — não decodificam o arquivo. Esta regra garante que o conteúdo
 * é realmente uma imagem válida antes de ir para o disco. Reutilizada por
 * todo upload de imagem institucional (banner, logo, favicon, coleções da
 * landing).
 */
final readonly class ValidImageContentRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile && @getimagesize($value->getRealPath()) === false) {
            $fail('O arquivo enviado não é uma imagem válida.');
        }
    }
}
