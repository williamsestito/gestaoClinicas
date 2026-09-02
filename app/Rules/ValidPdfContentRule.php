<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Mesmo racional de ValidImageContentRule: `mimes:pdf` só confia na
 * extensão/MIME declarados pelo cliente. Confere a assinatura real do
 * arquivo (`%PDF-` nos primeiros bytes) antes de aceitar o upload.
 */
final readonly class ValidPdfContentRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $handle = @fopen($value->getRealPath(), 'rb');

        if ($handle === false) {
            $fail('O arquivo enviado não pôde ser lido.');

            return;
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            $fail('O arquivo enviado não é um PDF válido.');
        }
    }
}
