<?php

namespace Xoshbin\Pertuk\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Xoshbin\Pertuk\PertukServiceProvider;

class TestCase extends Orchestra
{
    protected string $testDocsPath;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Xoshbin\\Pertuk\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Create a temporary docs directory for testing
        $this->createTestDocsDirectory();
    }

    protected function tearDown(): void
    {
        // Clean up test docs directory
        $this->cleanupTestDocsDirectory();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            PertukServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Initialize test docs path
        $this->testDocsPath = sys_get_temp_dir().'/pertuk-test-docs-'.uniqid();

        // Set up Pertuk configuration for testing
        config()->set('pertuk.root', $this->testDocsPath);
        config()->set('pertuk.cache_ttl', 60); // Short TTL for testing
        config()->set('pertuk.route_prefix', 'docs');
        config()->set('pertuk.enabled', true);
        config()->set('pertuk.exclude', ['.DS_Store', 'README.md', 'Developers']);
        config()->set('pertuk.default_order', 1000);

        // Set up app locale for testing
        config()->set('app.locale', 'en');
        config()->set('app.fallback_locale', 'en');
        config()->set('app.name', 'Test App');

        // Set testing environment
        config()->set('app.env', 'testing');
    }

    protected function getTestDocsPath(): string
    {
        return $this->testDocsPath;
    }

    protected function createTestDocsDirectory(): void
    {
        $docsPath = $this->getTestDocsPath();

        if (! File::exists($docsPath)) {
            File::makeDirectory($docsPath, 0755, true);
        }
    }

    protected function cleanupTestDocsDirectory(): void
    {
        $docsPath = $this->getTestDocsPath();

        if (File::exists($docsPath)) {
            File::deleteDirectory($docsPath);
        }
    }

    protected function createTestMarkdownFile(string $filename, string $content, string $subdirectory = ''): string
    {
        $docsPath = $this->getTestDocsPath();
        $fullPath = $subdirectory ? $docsPath.'/'.$subdirectory : $docsPath;

        if (! File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        $filePath = $fullPath.'/'.$filename;
        File::put($filePath, $content);

        return $filePath;
    }
}
