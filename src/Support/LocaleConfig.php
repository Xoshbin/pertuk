<?php

namespace Xoshbin\Pertuk\Support;

class LocaleConfig
{
    public static function rootLang(): ?string
    {
        return config('pertuk.locales.root.lang');
    }

    public static function rootKey(): ?string
    {
        return isset(config('pertuk.locales')['root']) ? 'root' : null;
    }

    public static function secondaryCodes(): array
    {
        return array_values(array_filter(
            array_keys(config('pertuk.locales', [])),
            fn ($key) => $key !== 'root'
        ));
    }

    public static function allLangs(): array
    {
        $langs = [];
        if ($root = self::rootLang()) {
            $langs[] = $root;
        }

        return array_merge($langs, self::secondaryCodes());
    }

    public static function isRootLang(string $lang): bool
    {
        return $lang === self::rootLang();
    }

    public static function isSecondary(string $code): bool
    {
        return in_array($code, self::secondaryCodes());
    }

    public static function isRtl(string $code): bool
    {
        $locales = config('pertuk.locales', []);

        if ($code === self::rootLang() && isset($locales['root'])) {
            return ($locales['root']['dir'] ?? 'ltr') === 'rtl';
        }

        return ($locales[$code]['dir'] ?? 'ltr') === 'rtl';
    }

    public static function label(string $code): string
    {
        $locales = config('pertuk.locales', []);

        if ($code === self::rootLang() && isset($locales['root'])) {
            return $locales['root']['label'] ?? $code;
        }

        return $locales[$code]['label'] ?? $code;
    }

    public static function versions(): array
    {
        return config('pertuk.versions', []);
    }

    public static function langDir(string $code): string
    {
        return self::isRtl($code) ? 'rtl' : 'ltr';
    }
}
