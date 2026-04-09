<?php

use Illuminate\Support\Facades\Http;
use Xoshbin\Pertuk\Services\Source\Exceptions\GitHubRateLimitException;
use Xoshbin\Pertuk\Services\Source\Exceptions\GitHubRequestException;
use Xoshbin\Pertuk\Services\Source\GitHubClient;

it('fetches the recursive tree and returns sha + entries', function () {
    Http::fake([
        'api.github.com/repos/acme/docs/git/trees/main*' => Http::response([
            'sha' => 'tree-sha-abc',
            'tree' => [
                ['path' => 'docs/en/intro.md', 'type' => 'blob', 'sha' => 'blob-1'],
                ['path' => 'docs/en',          'type' => 'tree', 'sha' => 'tree-2'],
            ],
            'truncated' => false,
        ], 200),
    ]);

    $client = new GitHubClient('acme/docs', 'main', token: null);

    $result = $client->fetchTree();

    expect($result['sha'])->toBe('tree-sha-abc')
        ->and($result['tree'])->toHaveCount(2)
        ->and($result['tree'][0]['path'])->toBe('docs/en/intro.md');
});

it('sends an Authorization header when a token is configured', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['sha' => 't', 'tree' => [], 'truncated' => false], 200),
    ]);

    (new GitHubClient('acme/docs', 'main', token: 'ghp_secret'))->fetchTree();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer ghp_secret');
    });
});

it('throws GitHubRateLimitException on 403 with rate limit headers', function () {
    Http::fake([
        'api.github.com/*' => Http::response(
            ['message' => 'API rate limit exceeded'],
            403,
            ['X-RateLimit-Remaining' => '0']
        ),
    ]);

    $client = new GitHubClient('acme/docs', 'main', token: null);

    expect(fn () => $client->fetchTree())->toThrow(GitHubRateLimitException::class);
});

it('throws GitHubRequestException on other HTTP errors', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $client = new GitHubClient('acme/does-not-exist', 'main', token: null);

    expect(fn () => $client->fetchTree())->toThrow(GitHubRequestException::class);
});

it('fetches raw file content from raw.githubusercontent.com', function () {
    Http::fake([
        'raw.githubusercontent.com/acme/docs/main/docs/en/intro.md' => Http::response(
            "# Intro\n\nHello.",
            200
        ),
    ]);

    $client = new GitHubClient('acme/docs', 'main', token: null);

    expect($client->fetchRaw('docs/en/intro.md'))->toBe("# Intro\n\nHello.");
});

it('sends Authorization header on raw fetches when token is set', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('ok', 200),
    ]);

    (new GitHubClient('acme/docs', 'main', token: 'ghp_secret'))->fetchRaw('docs/en/intro.md');

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://raw.githubusercontent.com/')
            && $request->hasHeader('Authorization', 'Bearer ghp_secret');
    });
});
