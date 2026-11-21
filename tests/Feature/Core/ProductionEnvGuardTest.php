<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * Runs artisan in a separate process the way `docker build` does: production, no secrets yet.
 */
function artisanInProductionWithoutSecrets(string $command): ProcessResult
{
    return Process::path(base_path())
        ->env(['APP_ENV' => 'production', 'APP_KEY' => '', 'DB_PASSWORD' => ''])
        ->run(['php', 'artisan', $command, '--no-ansi']);
}

it('lets package:discover run during the image build before secrets exist', function () {
    $result = artisanInProductionWithoutSecrets('package:discover');

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());
});

it('stops other commands in production when required env is missing', function () {
    $result = artisanInProductionWithoutSecrets('about');

    expect($result->failed())->toBeTrue()
        ->and($result->output().$result->errorOutput())->toContain('Missing environment variables: APP_KEY, DB_PASSWORD');
});
