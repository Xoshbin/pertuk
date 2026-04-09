<?php

use Illuminate\Http\Client\Factory as HttpFactory;
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
    Http::swap(new HttpFactory); // Reset all stubs and recorded history before the second sync.
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

    Http::swap(new HttpFactory); // Reset all stubs and recorded history before the second sync.
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

it('ensureFile is a no-op when the file already exists locally', function () {
    File::ensureDirectoryExists($this->ghCachePath.'/en');
    File::put($this->ghCachePath.'/en/intro.md', '# Cached');

    Http::fake();

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $source->ensureFile('en/intro.md');

    Http::assertNothingSent();
    expect(File::get($this->ghCachePath.'/en/intro.md'))->toBe('# Cached');
});

it('ensureFile downloads a single blob when the file is missing', function () {
    Http::fake([
        'raw.githubusercontent.com/acme/docs/main/docs/en/intro.md' => Http::response('# Fresh', 200),
    ]);

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $source->ensureFile('en/intro.md');

    expect(File::get($this->ghCachePath.'/en/intro.md'))->toBe('# Fresh');
});

it('ensureAsset returns the absolute path for an asset already on disk', function () {
    config()->set('pertuk.assets_path', 'assets');

    File::ensureDirectoryExists($this->ghCachePath.'/assets');
    File::put($this->ghCachePath.'/assets/logo.png', 'fake-png');

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    expect($source->ensureAsset('logo.png'))->toBe(
        realpath($this->ghCachePath.'/assets/logo.png')
    );
});

it('ensureAsset downloads a missing asset then returns its absolute path', function () {
    config()->set('pertuk.assets_path', 'assets');

    Http::fake([
        'raw.githubusercontent.com/acme/docs/main/docs/assets/logo.png' => Http::response('fake-png', 200),
    ]);

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $absolute = $source->ensureAsset('logo.png');

    expect($absolute)->not->toBeNull();
    expect(File::get($absolute))->toBe('fake-png');
});

it('ensureAsset returns null when the asset is missing both locally and upstream', function () {
    config()->set('pertuk.assets_path', 'assets');

    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('Not Found', 404),
    ]);

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    expect($source->ensureAsset('missing.png'))->toBeNull();
});

it('ensureAsset rejects path traversal', function () {
    config()->set('pertuk.assets_path', 'assets');

    File::ensureDirectoryExists($this->ghCachePath.'/assets');
    File::put($this->ghCachePath.'/secret.txt', 'secret');

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    expect($source->ensureAsset('../secret.txt'))->toBeNull();
});

it('ensureFile rejects path traversal without calling HTTP', function () {
    Http::fake();

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    $source->ensureFile('../etc/passwd');

    Http::assertNothingSent();
});

it('availableVersions scans the cache path for version folders containing a locale', function () {
    config()->set('pertuk.supported_locales', ['en']);
    config()->set('pertuk.exclude_versions', []);

    File::ensureDirectoryExists($this->ghCachePath.'/v1.0/en');
    File::put($this->ghCachePath.'/v1.0/en/x.md', '# v1');

    File::ensureDirectoryExists($this->ghCachePath.'/v2.0/en');
    File::put($this->ghCachePath.'/v2.0/en/x.md', '# v2');

    $source = new GitHubSource(
        client: new GitHubClient('acme/docs', 'main', token: null),
        repo: 'acme/docs',
        branch: 'main',
        path: 'docs',
        cachePath: $this->ghCachePath,
    );

    expect($source->availableVersions())->toBe(['v2.0', 'v1.0']);
});
