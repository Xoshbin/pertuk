<?php

use Xoshbin\Pertuk\Services\Source\SourceDriver;

it('the pertuk:build command invokes warmAll on the bound source driver', function () {
    $docsPath = $this->getTestDocsPath();

    $spy = new class($docsPath) implements SourceDriver
    {
        public int $warmAllCalls = 0;

        public function __construct(private string $path) {}

        public function rootPath(): string
        {
            return $this->path;
        }

        public function warmAll(): void
        {
            $this->warmAllCalls++;
        }

        public function ensureFile(string $relativePath): void {}

        public function ensureAsset(string $relativePath): ?string
        {
            return null;
        }

        public function availableVersions(): array
        {
            return [];
        }
    };

    app()->instance(SourceDriver::class, $spy);

    $this->artisan('pertuk:build')->assertSuccessful();

    expect($spy->warmAllCalls)->toBe(1);
});
