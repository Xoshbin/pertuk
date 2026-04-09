<?php

use Illuminate\Support\Facades\File;
use Xoshbin\Pertuk\Services\Source\GitHubSource;
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

it('resolves GitHubSource from config when source is github', function () {
    $cachePath = sys_get_temp_dir().'/pertuk-cfg-'.uniqid();

    config()->set('pertuk.source', 'github');
    config()->set('pertuk.sources.github.repo', 'acme/docs');
    config()->set('pertuk.sources.github.branch', 'main');
    config()->set('pertuk.sources.github.path', 'docs');
    config()->set('pertuk.sources.github.token', null);
    config()->set('pertuk.sources.github.cache_path', $cachePath);

    app()->forgetInstance(SourceDriver::class);

    try {
        $resolved = app(SourceDriver::class);
        expect($resolved)->toBeInstanceOf(GitHubSource::class);
    } finally {
        if (is_dir($cachePath)) {
            File::deleteDirectory($cachePath);
        }
    }
});
