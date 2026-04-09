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

it('warmAll downloads blobs under the configured path into cachePath and writes a manifest', function () {
    Http::fake([
        'api.github.com/repos/acme/docs/git/trees/main*' => Http::response([
            'sha' => 'tree-1',
            'tree' => [
                ['path' => 'docs/en/intro.md',  'type' => 'blob', 'sha' => 'sha-intro'],
                ['path' => 'docs/en/guide.md',  'type' => 'blob', 'sha' => 'sha-guide'],
                ['path' => 'README.md',         'type' => 'blob', 'sha' => 'sha-readme'], // outside docs/, must be skipped
                ['path' => 'docs/en',           'type' => 'tree', 'sha' => 'sha-dir'],
            ],
            'truncated' => false,
        ], 200),
        'raw.githubusercontent.com/acme/docs/main/docs/en/intro.md' => Http::response('# Intro', 200),
        'raw.githubusercontent.com/acme/docs/main/docs/en/guide.md' => Http::response('# Guide', 200),
    ]);

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $source->warmAll();

    expect(File::get($this->ghCachePath.'/en/intro.md'))->toBe('# Intro');
    expect(File::get($this->ghCachePath.'/en/guide.md'))->toBe('# Guide');
    expect(File::exists($this->ghCachePath.'/README.md'))->toBeFalse();

    $manifest = json_decode(File::get($this->ghCachePath.'/.manifest.json'), true);
    expect($manifest['tree_sha'])->toBe('tree-1');
    expect($manifest['files']['en/intro.md'])->toBe('sha-intro');
    expect($manifest['files']['en/guide.md'])->toBe('sha-guide');
});

it('warmAll downloads zero blobs when re-run with the same tree sha and unchanged blob shas', function () {
    $treePayload = [
        'sha' => 'tree-1',
        'tree' => [
            ['path' => 'docs/en/intro.md', 'type' => 'blob', 'sha' => 'sha-intro'],
        ],
        'truncated' => false,
    ];

    Http::fake([
        'api.github.com/*' => Http::response($treePayload, 200),
        'raw.githubusercontent.com/*' => Http::response('# Intro', 200),
    ]);

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $source->warmAll();
    Http::fake(); // Reset the recorded history before the second sync.
    Http::fake([
        'api.github.com/*' => Http::response($treePayload, 200),
        'raw.githubusercontent.com/*' => Http::response('# Intro', 200),
    ]);

    $source->warmAll();

    // No raw fetch on the second run.
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'raw.githubusercontent.com');
    });
});

it('warmAll re-downloads only blobs whose sha changed', function () {
    $first = [
        'sha' => 'tree-1',
        'tree' => [
            ['path' => 'docs/en/intro.md', 'type' => 'blob', 'sha' => 'sha-intro-1'],
            ['path' => 'docs/en/guide.md', 'type' => 'blob', 'sha' => 'sha-guide-1'],
        ],
        'truncated' => false,
    ];

    Http::fake([
        'api.github.com/*' => Http::response($first, 200),
        'raw.githubusercontent.com/acme/docs/main/docs/en/intro.md' => Http::response('# Intro v1', 200),
        'raw.githubusercontent.com/acme/docs/main/docs/en/guide.md' => Http::response('# Guide v1', 200),
    ]);

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $source->warmAll();

    $second = [
        'sha' => 'tree-2',
        'tree' => [
            ['path' => 'docs/en/intro.md', 'type' => 'blob', 'sha' => 'sha-intro-1'], // unchanged
            ['path' => 'docs/en/guide.md', 'type' => 'blob', 'sha' => 'sha-guide-2'], // changed
        ],
        'truncated' => false,
    ];

    Http::fake();
    Http::fake([
        'api.github.com/*' => Http::response($second, 200),
        'raw.githubusercontent.com/acme/docs/main/docs/en/guide.md' => Http::response('# Guide v2', 200),
    ]);

    $source->warmAll();

    expect(File::get($this->ghCachePath.'/en/guide.md'))->toBe('# Guide v2');

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'docs/en/intro.md');
    });
});
