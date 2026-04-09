<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Source;

use Illuminate\Support\Facades\File;

trait ScansVersions
{
    /**
     * Scan the given root directory for version sub-directories that contain
     * at least one supported locale folder.
     *
     * @return array<int, string>
     */
    protected function scanVersions(string $root): array
    {
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
