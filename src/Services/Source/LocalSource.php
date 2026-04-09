<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

use Illuminate\Support\Facades\File;

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
        $root = $this->rootPath();
        $excludeVersions = (array) config('pertuk.exclude_versions', []);

        if (! File::exists($root)) {
            return [];
        }

        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);

        $versions = [];
        foreach (File::directories($root) as $directory) {
            $name = basename($directory);
            if (in_array($name, $excludeVersions, true)) {
                continue;
            }

            foreach ($supportedLocales as $locale) {
                if (File::isDirectory($directory.DIRECTORY_SEPARATOR.$locale)) {
                    $versions[] = $name;
                    break;
                }
            }
        }

        usort($versions, 'strnatcmp');

        return array_reverse($versions);
    }
}
