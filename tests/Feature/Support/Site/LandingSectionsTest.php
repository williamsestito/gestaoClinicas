<?php

declare(strict_types=1);

use App\Support\Site\LandingSections;

it('returns all known section types active by default when nothing is stored', function () {
    $normalized = LandingSections::normalize(null);

    expect($normalized)->toHaveCount(count(LandingSections::TYPES))
        ->and(collect($normalized)->pluck('type')->all())->toBe(LandingSections::TYPES)
        ->and(collect($normalized)->pluck('active')->unique()->all())->toBe([true]);
});

it('preserves the stored order and active flags for known types', function () {
    $stored = [
        ['type' => 'services', 'active' => false],
        ['type' => 'hero', 'active' => true],
    ];

    $normalized = LandingSections::normalize($stored);

    expect($normalized[0])->toBe(['type' => 'services', 'active' => false])
        ->and($normalized[1])->toBe(['type' => 'hero', 'active' => true]);
});

it('silently discards unknown section types', function () {
    $stored = [
        ['type' => 'hero', 'active' => true],
        ['type' => 'totally-unknown-type', 'active' => true],
    ];

    $normalized = LandingSections::normalize($stored);

    expect(collect($normalized)->pluck('type')->contains('totally-unknown-type'))->toBeFalse();
});

it('appends known types missing from the stored value, active by default', function () {
    $stored = [['type' => 'hero', 'active' => false]];

    $normalized = LandingSections::normalize($stored);

    expect($normalized)->toHaveCount(count(LandingSections::TYPES))
        ->and($normalized[0])->toBe(['type' => 'hero', 'active' => false]);

    $faq = collect($normalized)->firstWhere('type', 'faq');
    expect($faq)->toBe(['type' => 'faq', 'active' => true]);
});

it('treats a non-array stored value as empty instead of failing', function () {
    $normalized = LandingSections::normalize('not-an-array');

    expect($normalized)->toHaveCount(count(LandingSections::TYPES));
});
