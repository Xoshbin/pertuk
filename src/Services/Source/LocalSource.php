<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

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
        // Filled in by Task 5.
        return null;
    }

    public function availableVersions(): array
    {
        // Filled in by Task 4.
        return [];
    }
}
