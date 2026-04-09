<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class GitHubSource implements SourceDriver
{
    use ScansVersions;

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
        $tree = $this->client->fetchTree();
        $previous = $this->readManifest();

        $prefix = rtrim($this->path, '/').'/';
        $prefixLen = \strlen($prefix);

        $files = [];

        foreach ($tree['tree'] as $entry) {
            if (($entry['type'] ?? null) !== 'blob') {
                continue;
            }
            $repoPath = (string) ($entry['path'] ?? '');
            if ($prefix !== '/' && ! str_starts_with($repoPath, $prefix)) {
                continue;
            }

            $relative = substr($repoPath, $prefixLen);
            if ($relative === '') {
                continue;
            }

            $sha = (string) ($entry['sha'] ?? '');
            $cached = $previous['files'][$relative] ?? null;

            if ($cached !== $sha || ! File::exists($this->cachePath.'/'.$relative)) {
                $contents = $this->client->fetchRaw($repoPath);
                $this->writeFile($relative, $contents);
            }

            $files[$relative] = $sha;
        }

        $manifest = [
            'tree_sha' => $tree['sha'],
            'synced_at' => now()->toIso8601String(),
            'files' => $files,
        ];

        File::put($this->cachePath.'/.manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * @return array{tree_sha: string, synced_at: string, files: array<string,string>}
     */
    protected function readManifest(): array
    {
        $file = $this->cachePath.'/.manifest.json';
        if (! File::exists($file)) {
            return ['tree_sha' => '', 'synced_at' => '', 'files' => []];
        }

        $decoded = json_decode(File::get($file), true);
        if (! is_array($decoded)) {
            return ['tree_sha' => '', 'synced_at' => '', 'files' => []];
        }

        return [
            'tree_sha' => (string) ($decoded['tree_sha'] ?? ''),
            'synced_at' => (string) ($decoded['synced_at'] ?? ''),
            'files' => (array) ($decoded['files'] ?? []),
        ];
    }

    protected function writeFile(string $relative, string $contents): void
    {
        $absolute = $this->cachePath.DIRECTORY_SEPARATOR.ltrim($relative, '/');
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $contents);
    }

    public function ensureFile(string $relativePath): void
    {
        $relative = ltrim($relativePath, '/');

        // Guard against traversal: the resolved path must sit inside cachePath.
        $absolute = $this->cachePath.DIRECTORY_SEPARATOR.$relative;
        if (str_contains($relative, '..')) {
            return;
        }

        if (File::exists($absolute)) {
            return;
        }

        $repoPath = rtrim($this->path, '/').'/'.$relative;
        $contents = $this->client->fetchRaw($repoPath);
        $this->writeFile($relative, $contents);
    }

    public function ensureAsset(string $relativePath): ?string
    {
        if (str_contains($relativePath, '..')) {
            return null;
        }

        $assetsPath = (string) config('pertuk.assets_path', 'assets');
        $relative = trim($assetsPath, '/').'/'.ltrim($relativePath, '/');
        $absolute = $this->cachePath.DIRECTORY_SEPARATOR.$relative;

        if (! File::exists($absolute)) {
            try {
                $repoPath = rtrim($this->path, '/').'/'.$relative;
                $contents = $this->client->fetchRaw($repoPath);
            } catch (\Throwable) {
                return null;
            }

            $this->writeFile($relative, $contents);
        }

        $real = realpath($absolute);
        if ($real === false) {
            return null;
        }

        $realBase = realpath($this->cachePath);
        if ($realBase === false || (! str_starts_with($real, $realBase.DIRECTORY_SEPARATOR) && $real !== $realBase)) {
            return null;
        }

        return is_file($real) ? $real : null;
    }

    public function availableVersions(): array
    {
        return $this->scanVersions($this->rootPath());
    }
}
