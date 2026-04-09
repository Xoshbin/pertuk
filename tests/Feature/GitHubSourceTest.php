<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Xoshbin\Pertuk\Services\Source\GitHubClient;
use Xoshbin\Pertuk\Services\Source\GitHubSource;

beforeEach(function () {
    $this->ghCachePath = sys_get_temp_dir().'/pertuk-github-'.uniqid();
    File::ensureDirectoryExists($this->ghCachePath);
});

afterEach(function () {
    if (File::isDirectory($this->ghCachePath)) {
        File::deleteDirectory($this->ghCachePath);
    }
});

it('exposes the configured cache path as rootPath', function () {
    $client = new GitHubClient('acme/docs', 'main', token: null);

    $source = new GitHubSource(
        client: $client,
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    expect($source->rootPath())->toBe($this->ghCachePath);
});

it('throws when the repo is not in owner/name form', function () {
    $client = new GitHubClient('broken', 'main', token: null);

    expect(fn () => new GitHubSource(
        client: $client,
        repo: 'broken',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    ))->toThrow(InvalidArgumentException::class);
});
