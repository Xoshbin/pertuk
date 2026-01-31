<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Finder\SplFileInfo;
use Xoshbin\Pertuk\Services\Content\ContentProcessor;
use Xoshbin\Pertuk\Services\Markdown\MarkdownRenderer;

class DocumentationService
{
    private MarkdownRenderer $markdownRenderer;

    private ContentProcessor $contentProcessor;

    public function __construct(
        protected string $root,
        protected int $ttl,
        protected ?string $version = null,
        /** @var array<int,string> */
        protected array $excludeDirectories = [],
    ) {
        $this->markdownRenderer = new MarkdownRenderer;
        $this->contentProcessor = new ContentProcessor;
    }

    public static function make(?string $version = null): self
    {
        $root = config('pertuk.root', base_path('docs'));
        $ttl = (int) config('pertuk.cache_ttl', 3600);
        $excludeDirectories = (array) config('pertuk.exclude_directories', []);
        // 1. Explicit argument
        // 2. Auto-discover default (latest) if not set
        if (! $version) {
            $available = self::getAvailableVersions();
            if (! empty($available)) {
                $version = $available[0];
            }
        }

        return new self($root, $ttl, $version, $excludeDirectories);
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Automatically discover available version directories.
     *
     * @return array<int,string>
     */
    public static function getAvailableVersions(): array
    {
        $root = config('pertuk.root', base_path('docs'));
        $excludeVersions = (array) config('pertuk.exclude_versions', []);

        if (! File::exists($root)) {
            return [];
        }

        $directories = File::directories($root);
        $versions = [];

        foreach ($directories as $directory) {
            $name = basename($directory);
            if (in_array($name, $excludeVersions)) {
                continue;
            }

            // A valid version directory should contain at least one locale folder
            $supportedLocales = (array) config('pertuk.supported_locales', ['en']);
            $hasLocale = false;
            foreach ($supportedLocales as $locale) {
                if (File::isDirectory($directory.DIRECTORY_SEPARATOR.$locale)) {
                    $hasLocale = true;
                    break;
                }
            }

            if ($hasLocale) {
                $versions[] = $name;
            }
        }

        // Sort versions naturally (e.g., v10 > v2)
        usort($versions, 'strnatcmp');

        // Reverse to have latest version first
        return array_reverse($versions);
    }

    /**
     * List docs as a flat array with minimal metadata (for index/sidebar).
     * Scopes to the provided locale (or current application locale).
     *
     * @return array<string, array{slug:string,title:string,order:int,path:string,mtime:int}>
     */
    public function list(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $files = $this->getFiles($locale);

        $items = [];

        foreach ($files as $file) {
            $slug = $this->getSlugFromPath($file->getPathname());

            $parsed = $this->parseFrontMatter($file->getPathname());
            $title = $parsed['title'] ?? $this->inferTitle($file->getPathname());
            $order = (int) ($parsed['order'] ?? config('pertuk.default_order', 1000));

            $items[$slug] = [
                'slug' => $slug,
                'title' => $title,
                'order' => $order,
                'path' => $file->getPathname(),
                'mtime' => File::lastModified($file->getPathname()),
            ];
        }

        return collect($items)
            ->sortBy(['order', 'title'])
            ->all();
    }

    /**
     * Get all discovered slugs for all locales.
     * Useful for cache warming (pre-rendering).
     *
     * @return array<int, array{locale:string, slug:string}>
     */
    public function discoverAll(): array
    {
        $all = [];
        $locales = config('pertuk.supported_locales', ['en']);

        foreach ($locales as $locale) {
            foreach ($this->list($locale) as $item) {
                $all[] = [
                    'locale' => $locale,
                    'slug' => $item['slug'],
                ];
            }
        }

        return $all;
    }

    /**
     * Get all documentation files for a specific locale, respecting exclude rules.
     *
     * @return Collection<int, SplFileInfo>
     */
    protected function getFiles(string $locale): Collection
    {
        $versionPart = $this->version ? $this->version.DIRECTORY_SEPARATOR : '';
        $dir = $this->root.DIRECTORY_SEPARATOR.$versionPart.$locale;

        if (! File::exists($dir)) {
            // Fallback to non-versioned locale folder if versioned one doesn't exist
            if ($this->version) {
                $flatDir = $this->root.DIRECTORY_SEPARATOR.$locale;
                if (File::exists($flatDir)) {
                    $dir = $flatDir;
                } else {
                    return collect([]);
                }
            } else {
                return collect([]);
            }
        }

        return collect(File::allFiles($dir))
            ->filter(fn ($file) => Str::endsWith($file->getFilename(), '.md'))
            ->reject(function ($file) use ($dir) {
                $relPath = Str::after($file->getPathname(), rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

                // Check if filename is in exclude list
                if (in_array($file->getFilename(), $this->excludeDirectories, true)) {
                    return true;
                }

                // Check if any part of the path is in exclude list
                foreach ($this->excludeDirectories as $excludePattern) {
                    if (Str::contains($relPath, $excludePattern)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Get a single document by locale and slug.
     *
     * @return array{title:string, html:string, toc:array<int,array{level:int,id:string,text:string}>, breadcrumbs:array<int,array{title:string,slug:?string}>, mtime:int, etag:string, alternates:array, current_locale:string, current_version:?string}
     *
     * @throws FileNotFoundException
     */
    public function get(string $locale, string $slug): array
    {
        $path = $this->resolvePath($locale, $slug);
        if (! $path || ! File::exists($path)) {
            throw new FileNotFoundException("Doc not found for [{$locale}] slug: {$slug}");
        }

        $mtime = File::lastModified($path);
        $cacheKey = 'pertuk:docs:'.$locale.':'.md5($path.':'.$mtime);

        // Get cached value and validate it
        $cached = Cache::get($cacheKey);
        if ($cached !== null && $this->isValidCachedDocument($cached)) {
            return $cached;
        }

        // Generate fresh content and cache it
        $result = $this->generateDocumentData($path, $locale, $slug, $mtime);
        Cache::put($cacheKey, $result, $this->ttl);

        return $result;
    }

    /**
     * Validate that cached document data has the expected structure
     */
    private function isValidCachedDocument(mixed $data): bool
    {
        return is_array($data)
            && isset($data['title'], $data['html'], $data['toc'], $data['breadcrumbs'], $data['mtime'], $data['etag']);
    }

    /**
     * Generate fresh document data
     */
    private function generateDocumentData(string $path, string $locale, string $slug, int $mtime): array
    {
        $raw = File::get($path);
        try {
            $front = YamlFrontMatter::parse($raw);
            $content = $front->body();
            $meta = $front->matter();
        } catch (\Throwable $e) {
            Log::warning('Failed to parse front matter', ['path' => $path, 'e' => $e->getMessage()]);
            $content = $raw;
            $meta = [];
        }

        $converter = $this->markdownRenderer->getConverter();
        $html = (string) $converter->convert($content);

        // Inject heading IDs and build TOC from h2/h3
        [$htmlWithIds, $toc] = $this->contentProcessor->injectHeadingIdsAndToc($html);

        // Enhance links and images
        $htmlWithLinks = $this->contentProcessor->postProcessLinksAndImages($htmlWithIds, $locale, $slug, $this->version);

        $title = $meta['title'] ?? $this->contentProcessor->extractH1($htmlWithLinks) ?? $this->inferTitle($path);

        // Remove first H1 from the content to avoid duplicated page title in the view
        $htmlWithLinks = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $htmlWithLinks, 1) ?? $htmlWithLinks;

        $breadcrumbs = [
            ['title' => __('Documentation'), 'slug' => null],
        ];

        $etag = 'W/"'.substr(sha1($path.'|'.$mtime.'|'.strlen($htmlWithLinks)), 0, 27).'"';

        // Language alternates
        $alternates = $this->buildAlternates($locale, $slug);

        return [
            'title' => $title,
            'html' => $htmlWithLinks,
            'toc' => $toc,
            'breadcrumbs' => $breadcrumbs,
            'mtime' => $mtime,
            'etag' => $etag,
            'alternates' => $alternates,
            'current_locale' => $locale,
            'current_version' => $this->version,
        ];
    }

    /** Build a lightweight search index with content chunks for better relevancy. */
    /** @return array<int, array{id:string, slug:string, title:string, heading:?string, content:string, anchor:?string, locale:string}> */
    public function buildIndex(?string $locale = null): array
    {
        $index = [];
        // If specific locale requested, use it; otherwise use all supported
        $locales = $locale ? [$locale] : config('pertuk.supported_locales', ['en']);

        foreach ($locales as $loc) {
            foreach ($this->list($loc) as $item) {
                try {
                    $data = $this->get($loc, $item['slug']);
                    $chunks = $this->contentProcessor->extractChunks($data['html'], $item['slug'], $data['title']);
                    foreach ($chunks as $chunk) {
                        $chunk['locale'] = $loc;
                        $index[] = $chunk;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to index doc: {$loc}/{$item['slug']}", ['e' => $e->getMessage()]);
                }
            }
        }

        return $index;
    }

    /** @return array<string, mixed> */
    protected function parseFrontMatter(string $path): array
    {
        try {
            $raw = File::get($path);
            $front = YamlFrontMatter::parse($raw);

            return $front->matter();
        } catch (\Throwable $e) {
            Log::warning('Failed to parse front matter', ['path' => $path, 'e' => $e->getMessage()]);

            return [];
        }
    }

    protected function inferTitle(string $path): string
    {
        $raw = File::get($path);
        if (preg_match('/^\s*#\s+(.+)$/m', $raw, $m)) {
            return trim($m[1]);
        }

        return Str::of(basename($path, '.md'))->headline()->toString();
    }

    protected function resolvePath(string $locale, string $slug): ?string
    {
        $versionPart = $this->version ? $this->version.DIRECTORY_SEPARATOR : '';
        $path = $this->root.DIRECTORY_SEPARATOR.$versionPart.$locale.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $slug);

        if (! Str::endsWith($path, '.md')) {
            $path .= '.md';
        }

        if (File::exists($path)) {
            return $path;
        }

        // Fallback to non-versioned path if versioned one doesn't exist
        // This maintains backward compatibility for existing "flat" documentation
        if ($this->version) {
            $flatPath = $this->root.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $slug);
            if (! Str::endsWith($flatPath, '.md')) {
                $flatPath .= '.md';
            }

            if (File::exists($flatPath)) {
                return $flatPath;
            }
        }

        return null;
    }

    /**
     * Convert a file path back to a slug
     */
    public function getSlugFromPath(string $path): string
    {
        $root = rtrim($this->root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $rel = Str::after($path, $root);
        // $rel is "v1.0/en/slug.md" or "en/slug.md"

        $parts = explode(DIRECTORY_SEPARATOR, $rel);

        // Check if first part is a version
        $versions = self::getAvailableVersions();
        if (count($parts) > 2 && in_array($parts[0], $versions)) {
            // It's versioned, skip version and locale
            return Str::beforeLast(implode('/', array_slice($parts, 2)), '.md');
        }

        // It's likely flat or first part is locale
        if (count($parts) > 1) {
            // Skip locale
            return Str::beforeLast(implode('/', array_slice($parts, 1)), '.md');
        }

        return Str::beforeLast($rel, '.md');
    }

    protected function resolveRelativeDocLink(string $href, string $currentSlug, string $locale): string
    {
        // Resolve relative path against current slug path
        $currentDir = Str::of($currentSlug)->contains('/') ? Str::beforeLast($currentSlug, '/') : '';
        $target = ltrim($href, './');
        if (Str::endsWith($target, '.md')) {
            $target = Str::beforeLast($target, '.md');
        }
        $slug = trim($currentDir ? ($currentDir.'/'.$target) : $target, '/');

        return url('/'.config('pertuk.route_prefix', 'docs').'/'.($this->version ? $this->version.'/' : '').$locale.'/'.$slug);
    }

    /**
     * Build language alternate links for a given slug based on available files.
     *
     * @return array<int, array{locale:string, label:string, url:string, active:bool}>
     */
    protected function buildAlternates(string $locale, string $slug): array
    {
        // Determine base slug (already passed as simple slug)
        $supported = (array) config('pertuk.supported_locales', ['en']);

        $labels = (array) config('pertuk.locale_labels', []);
        $prefix = config('pertuk.route_prefix', 'docs');

        $alternates = [];
        foreach ($supported as $loc) {
            $path = $this->resolvePath($loc, $slug);
            if ($path) {
                $alternates[] = [
                    'locale' => $loc,
                    'label' => $labels[$loc] ?? strtoupper($loc),
                    'url' => url('/'.$prefix.'/'.($this->version ? $this->version.'/' : '').$loc.'/'.$slug),
                    'active' => $loc === $locale,
                ];
            }
        }

        return $alternates;
    }
}
