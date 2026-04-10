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

it('returns versions from the configuration', function () {
    config()->set('pertuk.versions', ['v2.0', 'v1.0']);

    $source = new LocalSource;

    expect($source->availableVersions())->toBe(['v2.0', 'v1.0']);
});

it('resolves an existing asset under the assets path', function () {
    config()->set('pertuk.sources.local.root', $this->getTestDocsPath());
    config()->set('pertuk.assets_path', 'assets');

    $assetsDir = $this->getTestDocsPath().'/assets';
    mkdir($assetsDir, 0777, true);
    file_put_contents($assetsDir.'/logo.png', 'fake-png');

    $source = new LocalSource;

    expect($source->ensureAsset('logo.png'))->toBe(realpath($assetsDir.'/logo.png'));
});

it('returns null for a missing asset', function () {
    config()->set('pertuk.sources.local.root', $this->getTestDocsPath());
    config()->set('pertuk.assets_path', 'assets');

    $source = new LocalSource;

    expect($source->ensureAsset('does-not-exist.png'))->toBeNull();
});

it('rejects path traversal outside the assets directory', function () {
    config()->set('pertuk.sources.local.root', $this->getTestDocsPath());
    config()->set('pertuk.assets_path', 'assets');

    mkdir($this->getTestDocsPath().'/assets', 0777, true);
    file_put_contents($this->getTestDocsPath().'/secret.txt', 'secret');

    $source = new LocalSource;

    expect($source->ensureAsset('../secret.txt'))->toBeNull();
});
