<?php

namespace Xoshbin\Pertuk;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Xoshbin\Pertuk\Pertuk as PertukCore;
use Xoshbin\Pertuk\Services\DocumentationService;
use Xoshbin\Pertuk\Services\Source\GitHubClient;
use Xoshbin\Pertuk\Services\Source\GitHubSource;
use Xoshbin\Pertuk\Services\Source\LocalSource;
use Xoshbin\Pertuk\Services\Source\SourceDriver;

class PertukServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('pertuk')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasAssets()
            ->hasCommand(Console\Commands\BuildDocumentation::class);
    }

    public function packageRegistered(): void
    {
        // Bind core services
        $this->app->singleton(PertukCore::class);

        $this->app->singleton(SourceDriver::class, function ($app) {
            $source = (string) (config('pertuk.source') ?: 'local');

            return match ($source) {
                'local' => new LocalSource,
                'github' => (function () {
                    $cfg = (array) config('pertuk.sources.github', []);
                    $repo = (string) ($cfg['repo'] ?? '');
                    $branch = (string) ($cfg['branch'] ?? 'main');
                    $path = (string) ($cfg['path'] ?? 'docs');
                    $token = $cfg['token'] ?? null;
                    $cachePath = (string) ($cfg['cache_path'] ?? storage_path('app/pertuk/github'));

                    $client = new GitHubClient(
                        repo: $repo,
                        branch: $branch,
                        token: is_string($token) ? $token : null,
                    );

                    return new GitHubSource(
                        client: $client,
                        repo: $repo,
                        branch: $branch,
                        path: $path,
                        cachePath: $cachePath,
                    );
                })(),
                default => throw new \InvalidArgumentException(
                    "Unknown pertuk.source driver: [{$source}]. Supported: local, github."
                ),
            };
        });

        $this->app->bind(DocumentationService::class, function () {
            return DocumentationService::make();
        });
    }
}
