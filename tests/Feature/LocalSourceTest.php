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

it('discovers version directories that contain a supported locale folder', function () {
    config()->set('pertuk.sources.local.root', $this->getTestDocsPath());
    config()->set('pertuk.supported_locales', ['en', 'ckb']);
    config()->set('pertuk.exclude_versions', []);

    $this->createTestMarkdownFile('test.md', '# v1.0', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# v2.0', '', 'en', 'v2.0');

    $source = new LocalSource;

    expect($source->availableVersions())->toBe(['v2.0', 'v1.0']); // latest first
});

it('excludes versions listed in pertuk.exclude_versions', function () {
    config()->set('pertuk.sources.local.root', $this->getTestDocsPath());
    config()->set('pertuk.supported_locales', ['en']);
    config()->set('pertuk.exclude_versions', ['archived']);

    $this->createTestMarkdownFile('test.md', '# v1.0', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# archived', '', 'en', 'archived');

    $source = new LocalSource;

    expect($source->availableVersions())->toContain('v1.0');
    expect($source->availableVersions())->not->toContain('archived');
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
