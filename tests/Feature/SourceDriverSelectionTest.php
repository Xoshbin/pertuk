<?php

use Xoshbin\Pertuk\Services\Source\LocalSource;
use Xoshbin\Pertuk\Services\Source\SourceDriver;

it('resolves LocalSource from the container when source is local', function () {
    config()->set('pertuk.source', 'local');

    $resolved = app(SourceDriver::class);

    expect($resolved)->toBeInstanceOf(LocalSource::class);
});

it('resolves LocalSource by default when source config is absent', function () {
    config()->offsetUnset('pertuk.source');

    $resolved = app(SourceDriver::class);

    expect($resolved)->toBeInstanceOf(LocalSource::class);
});
