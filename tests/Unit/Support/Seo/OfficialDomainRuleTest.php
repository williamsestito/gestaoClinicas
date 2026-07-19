<?php

declare(strict_types=1);

use App\Rules\OfficialDomainRule;

function validateOfficialDomain(mixed $value): array
{
    $errors = [];
    (new OfficialDomainRule)->validate('official_domain', $value, function (string $message) use (&$errors) {
        $errors[] = $message;
    });

    return $errors;
}

it('accepts a well-formed domain', function () {
    expect(validateOfficialDomain('clinicaexemplo.com.br'))->toBe([])
        ->and(validateOfficialDomain('sub.clinica.com'))->toBe([]);
});

it('accepts an empty value — the domain is optional', function () {
    expect(validateOfficialDomain(null))->toBe([])
        ->and(validateOfficialDomain(''))->toBe([]);
});

it('rejects a value with a protocol', function () {
    expect(validateOfficialDomain('https://clinicaexemplo.com.br'))->not->toBe([]);
});

it('rejects a value with a path, query string or fragment', function () {
    expect(validateOfficialDomain('clinicaexemplo.com.br/pagina'))->not->toBe([])
        ->and(validateOfficialDomain('clinicaexemplo.com.br?utm_source=x'))->not->toBe([])
        ->and(validateOfficialDomain('clinicaexemplo.com.br#secao'))->not->toBe([]);
});

it('rejects a value with dangerous characters', function () {
    expect(validateOfficialDomain('<script>alert(1)</script>'))->not->toBe([])
        ->and(validateOfficialDomain('javascript:alert(1)'))->not->toBe([]);
});

it('rejects a malformed hostname', function () {
    expect(validateOfficialDomain('-clinica.com'))->not->toBe([])
        ->and(validateOfficialDomain('clinica'))->not->toBe([])
        ->and(validateOfficialDomain('clinica..com'))->not->toBe([]);
});
