<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PostalCodeLookup;
use App\Support\Documents\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostalCodeLookupController extends Controller
{
    public function __invoke(Request $request, string $postalCode, PostalCodeLookup $lookup): JsonResponse
    {
        $digits = Document::onlyDigits($postalCode);

        if (strlen($digits) !== 8) {
            throw ValidationException::withMessages(['postal_code' => 'Informe um CEP com 8 dígitos.']);
        }

        $result = $lookup->lookup($digits);

        if (! $result) {
            return response()->json(['message' => 'CEP não encontrado.'], 404);
        }

        return response()->json($result->toArray());
    }
}
