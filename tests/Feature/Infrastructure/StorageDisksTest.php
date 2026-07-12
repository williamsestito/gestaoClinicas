<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('writes and reads a file on the local disk', function () {
    Storage::fake('local');

    $path = 'infra-test-'.Str::random(8).'.txt';
    Storage::disk('local')->put($path, 'ok');

    expect(Storage::disk('local')->get($path))->toBe('ok');
});

it('has the MinIO/S3 disk configured with the expected values', function () {
    expect(config('filesystems.disks.s3.driver'))->toBe('s3')
        ->and(config('filesystems.disks.s3.use_path_style_endpoint'))->toBeTrue()
        ->and(config('filesystems.disks.s3.endpoint'))->not->toBeEmpty();
});
