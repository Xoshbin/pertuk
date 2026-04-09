<?php

use Xoshbin\Pertuk\Services\Source\LocalSource;

it('returns the configured sources.local.root as its rootPath', function () {
    config()->set('pertuk.sources.local.root', '/tmp/pertuk-local-root-primary');
    config()->set('pertuk.root', '/tmp/pertuk-legacy-root');

    $source = new LocalSource;

    expect($source->rootPath())->toBe('/tmp/pertuk-local-root-primary');
});

it('falls back to the legacy top-level pertuk.root when sources.local.root is null', function () {
    config()->set('pertuk.sources.local.root', null);
    config()->set('pertuk.root', '/tmp/pertuk-legacy-root');

    $source = new LocalSource;

    expect($source->rootPath())->toBe('/tmp/pertuk-legacy-root');
});
