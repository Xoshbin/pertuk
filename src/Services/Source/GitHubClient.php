<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Xoshbin\Pertuk\Services\Source\Exceptions\GitHubRateLimitException;
use Xoshbin\Pertuk\Services\Source\Exceptions\GitHubRequestException;

class GitHubClient
{
    public function __construct(
        protected string $repo,
        protected string $branch,
        protected ?string $token,
    ) {}

    /**
     * @return array{sha: string, tree: array<int, array<string, mixed>>, truncated: bool}
     */
    public function fetchTree(): array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/git/trees/%s?recursive=1',
            $this->repo,
            $this->branch
        );

        $response = $this->request($url);

        /** @var array{sha: string, tree: array<int, array<string, mixed>>, truncated: bool} $data */
        $data = $response->json();

        return $data;
    }

    public function fetchRaw(string $pathInRepo): string
    {
        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s',
            $this->repo,
            $this->branch,
            ltrim($pathInRepo, '/')
        );

        $response = $this->request($url);

        return (string) $response->body();
    }

    protected function request(string $url): Response
    {
        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'pertuk-docs',
        ]);

        if ($this->token !== null && $this->token !== '') {
            $request = $request->withToken($this->token);
        }

        $response = $request->get($url);

        if ($response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0') {
            throw new GitHubRateLimitException($url, (string) $response->body());
        }

        if (! $response->successful()) {
            throw new GitHubRequestException(
                $response->status(),
                $url,
                (string) $response->body()
            );
        }

        return $response;
    }
}
