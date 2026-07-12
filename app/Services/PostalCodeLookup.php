<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;

interface PostalCodeLookup
{
    /**
     * Consulta um CEP brasileiro (8 dígitos). Retorna null quando o CEP não
     * existe ou o serviço externo está indisponível — nunca lança exceção
     * para quem chama, para não travar o formulário de cadastro.
     */
    public function lookup(string $postalCode): ?PostalCodeResult;
}
