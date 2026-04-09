<?php

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
    config()->set('pertuk.source', 'github');
    config()->set('pertuk.sources.github.repo', 'acme/docs');
    config()->set('pertuk.sources.github.branch', 'main');
    config()->set('pertuk.sources.github.path', 'docs');
    config()->set('pertuk.sources.github.token', null);
    config()->set('pertuk.sources.github.cache_path', sys_get_temp_dir().'/pertuk-cfg-'.uniqid());

    // Force the container to re-resolve the singleton under the new config.
    app()->forgetInstance(\Xoshbin\Pertuk\Services\Source\SourceDriver::class);

    $resolved = app(\Xoshbin\Pertuk\Services\Source\SourceDriver::class);

    expect($resolved)->toBeInstanceOf(GitHubSource::class);
});
