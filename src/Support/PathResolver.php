<?php

namespace Xoshbin\Pertuk\Support;

class PathResolver
{
    /**
     * @return array{locale: string|null, version: string|null, slug: string}
     */
    public static function resolve(string $path): array
    {
        $segments = array_filter(explode('/', $path));
        $version = null;
        $locale = null;

        // Check for version
        if (! empty($segments) && in_array(reset($segments), LocaleConfig::versions())) {
            $version = array_shift($segments);
        }

        // Check for secondary locale
        if (! empty($segments) && LocaleConfig::isSecondary(reset($segments))) {
            $locale = array_shift($segments);
        } else {
            $locale = LocaleConfig::rootLang();
        }

        $slug = implode('/', $segments) ?: 'index';

        return [
            'locale' => $locale,
            'version' => $version,
            'slug' => $slug,
        ];
    }
}
