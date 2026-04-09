<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

interface SourceDriver
{
    /** Absolute filesystem path that DocumentationService reads markdown from. */
    public function rootPath(): string;

    /**
     * Eager warm — called from `pertuk:build`. Implementations should make sure
     * every file required to render the docs tree exists under rootPath().
     *
     * Local: no-op. GitHub: full tree sync.
     */
    public function warmAll(): void;

    /**
     * Ensure a single file (path relative to rootPath()) exists on disk before
     * DocumentationService reads it. Called on every request; must be fast
     * when the file is already present.
     *
     * Local: no-op. GitHub: download the single file if missing.
     */
    public function ensureFile(string $relativePath): void;

    /**
     * Resolve a documentation asset. `$relativePath` is relative to
     * `config('pertuk.assets_path')` under rootPath().
     *
     * Returns the absolute path to the on-disk asset (downloading it first if
     * the driver needs to), or null if the asset doesn't exist in the source.
     */
    public function ensureAsset(string $relativePath): ?string;

    /**
     * Directory-based version discovery under rootPath(). Same semantics as
     * the previous static DocumentationService::getAvailableVersions().
     *
     * @return array<int, string>
     */
    public function availableVersions(): array;
}
