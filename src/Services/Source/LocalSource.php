<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

use Xoshbin\Pertuk\Support\LocaleConfig;

class LocalSource implements SourceDriver
{
    public function rootPath(): string
    {
        $configured = config('pertuk.sources.local.root');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // Legacy fallback — honoured for installs that haven't migrated.
        return (string) config('pertuk.root', base_path('docs'));
    }

    public function warmAll(): void
    {
        // No-op. Local files are assumed to already exist.
    }

    public function ensureFile(string $relativePath): void
    {
        // No-op. Local files are assumed to already exist.
    }

    public function ensureAsset(string $relativePath): ?string
    {
        $assetsPath = (string) config('pertuk.assets_path', 'assets');
        $base = rtrim($this->rootPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$assetsPath;
        $candidate = $base.DIRECTORY_SEPARATOR.ltrim($relativePath, '/');

        $real = realpath($candidate);
        $realBase = realpath($base);

        if ($real === false || $realBase === false) {
            return null;
        }

        // Reject traversal: resolved path must sit inside the assets dir.
        if (! str_starts_with($real, $realBase.DIRECTORY_SEPARATOR) && $real !== $realBase) {
            return null;
        }

        if (! is_file($real)) {
            return null;
        }

        return $real;
    }

    public function availableVersions(): array
    {
        return LocaleConfig::versions();
    }
}
