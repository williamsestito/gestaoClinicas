<?php

declare(strict_types=1);

use App\Enums\LegalEntityType;
use App\Support\Documents\Document;

it('accepts a valid CPF, with or without mask', function () {
    expect(Document::isValid(LegalEntityType::Individual, '111.444.777-35'))->toBeTrue()
        ->and(Document::isValid(LegalEntityType::Individual, '11144477735'))->toBeTrue();
});

it('removes the mask when building a CPF document', function () {
    $document = Document::fromCpf('111.444.777-35');

    expect($document->digits)->toBe('11144477735');
});

it('rejects an invalid CPF', function () {
    expect(Document::isValid(LegalEntityType::Individual, '111.444.777-36'))->toBeFalse()
        ->and(Document::isValid(LegalEntityType::Individual, '00000000000'))->toBeFalse();
});

it('accepts a valid CNPJ, with or without mask', function () {
    expect(Document::isValid(LegalEntityType::Company, '11.222.333/0001-81'))->toBeTrue()
        ->and(Document::isValid(LegalEntityType::Company, '11222333000181'))->toBeTrue();
});

it('rejects an invalid CNPJ', function () {
    expect(Document::isValid(LegalEntityType::Company, '11.222.333/0001-82'))->toBeFalse();
});

it('rejects a document with the wrong number of digits for the type', function () {
    // CPF (11 dígitos) informado como se fosse CNPJ.
    expect(Document::isValid(LegalEntityType::Company, '11144477735'))->toBeFalse();
});

it('masks the document keeping only the last two digits', function () {
    $document = Document::fromCpf('111.444.777-35');

    expect($document->masked())->toBe('*********35');
});
