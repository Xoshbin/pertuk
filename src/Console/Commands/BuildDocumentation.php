<?php

namespace Xoshbin\Pertuk\Console\Commands;

use Illuminate\Console\Command;
use Xoshbin\Pertuk\Services\DocumentationService;
use Xoshbin\Pertuk\Services\Source\SourceDriver;

class BuildDocumentation extends Command
{
    protected $signature = 'pertuk:build';

    protected $description = 'Pre-render all documentation to the cache to improve performance.';

    public function handle(DocumentationService $docs, SourceDriver $source): int
    {
        $this->info('Starting documentation build...');

        $this->info('Warming documentation source...');
        $source->warmAll();

        $slugs = $docs->discoverAll();
        $count = count($slugs);

        $this->info("Found {$count} documentation files.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($slugs as $item) {
            try {
                $docs->get($item['locale'], $item['slug']);
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to build {$item['locale']}/{$item['slug']}: ".$e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Documentation build completed successfully.');

        return self::SUCCESS;
    }
}
