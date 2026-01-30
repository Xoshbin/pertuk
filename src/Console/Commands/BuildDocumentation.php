<?php

namespace Xoshbin\Pertuk\Console\Commands;

use Illuminate\Console\Command;
use Xoshbin\Pertuk\Services\DocumentationService;

class BuildDocumentation extends Command
{
    protected $signature = 'pertuk:build';

    protected $description = 'Pre-render all documentation to the cache to improve performance.';

    public function handle(DocumentationService $docs): int
    {
        $this->info('Starting documentation build...');

        $slugs = $docs->discoverAllSlugs();
        $count = count($slugs);

        $this->info("Found {$count} documentation files.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($slugs as $slug) {
            try {
                $docs->get($slug);
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to build slug: {$slug} - ".$e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Documentation build completed successfully.');

        return self::SUCCESS;
    }
}
