<?php

namespace Xoshbin\Pertuk\Support;

class PertukUrl
{
    public static function doc(string $slug, ?string $locale = null, ?string $version = null): string
    {
        $prefix = config('pertuk.route_prefix', 'docs');
        $parts = [$prefix];

        if ($version) {
            $parts[] = $version;
        }

        if ($locale && $locale !== LocaleConfig::rootLang()) {
            if (! LocaleConfig::isSecondary($locale)) {
                throw new \InvalidArgumentException("Unknown locale [{$locale}] passed to PertukUrl::doc()");
            }
            $parts[] = $locale;
        }

        if ($slug !== 'index') {
            foreach (explode('/', $slug) as $segment) {
                $parts[] = $segment;
            }
        }

        return '/'.implode('/', $parts);
    }
}
