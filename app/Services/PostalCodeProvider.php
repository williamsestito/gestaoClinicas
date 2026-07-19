<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;

interface PostalCodeProvider
{
    /**
     * Consulta um provedor específico de CEP. `$digits` já vem normalizado
     * e validado (8 dígitos) por quem chama. Nunca lança exceção — qualquer
     * falha (timeout, indisponibilidade, CEP inexistente) retorna null, para
     * que o PostalCodeLookupChain tente o próximo provedor.
     */
    public function fetch(string $digits): ?PostalCodeResult;
}
