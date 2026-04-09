<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class GitHubSource implements SourceDriver
{
    public function __construct(
        protected GitHubClient $client,
        protected string $repo,
        protected string $branch,
        protected string $path,
        protected string $cachePath,
    ) {
        if (! preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repo)) {
            throw new InvalidArgumentException(
                "pertuk.sources.github.repo must be in 'owner/name' form, got: [{$repo}]"
            );
        }

        File::ensureDirectoryExists($this->cachePath);
    }

    public function rootPath(): string
    {
        return $this->cachePath;
    }

    public function warmAll(): void
    {
        // Implemented in Task 13.
    }

    public function ensureFile(string $relativePath): void
    {
        // Implemented in Task 15.
    }

    public function ensureAsset(string $relativePath): ?string
    {
        // Implemented in Task 16.
        return null;
    }

    public function availableVersions(): array
    {
        // Implemented in Task 17.
        return [];
    }
}
