<?php

namespace Xoshbin\Pertuk;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
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
            ->hasRoute('web');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(DocumentationService::class, function () {
            return DocumentationService::make();
        });
    }
}
