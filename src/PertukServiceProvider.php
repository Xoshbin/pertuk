<?php

namespace Xoshbin\Pertuk;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Xoshbin\Pertuk\Pertuk as PertukCore;
use Xoshbin\Pertuk\Services\DocumentationService;

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

        $this->app->bind(DocumentationService::class, function () {
            return DocumentationService::make();
        });
    }
}
