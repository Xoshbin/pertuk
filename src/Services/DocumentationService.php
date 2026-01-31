<?php

namespace Xoshbin\Pertuk\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Spatie\CommonMarkShikiHighlighter\HighlightCodeExtension;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Xoshbin\Pertuk\Extensions\Admonition\AdmonitionExtension;
use Xoshbin\Pertuk\Extensions\Component\ComponentExtension;

class DocumentationService
{
    public function __construct(
        protected string $root,
        protected int $ttl,
        protected ?string $version = null,
        /** @var array<int,string> */
        protected array $excludeDirectories = [], // Renamed from $exclude
    ) {}

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
     * Prioritizes documents in the current application locale.
     *
     * @return array<string, array{slug:string,title:string,order:int,path:string,mtime:int}>
     */
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
     * @return \Illuminate\Support\Collection<int, \Symfony\Component\Finder\SplFileInfo>
     */
    protected function getFiles(string $locale): \Illuminate\Support\Collection
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
     * Get a single document by slug.
     *
     * @return array{title:string, html:string, toc:array<int,array{level:int,id:string,text:string}>, breadcrumbs:array<int,array{title:string,slug:?string}>, mtime:int, etag:string}
     *
     * @throws FileNotFoundException
     */
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
    private function isValidCachedDocument($data): bool
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

        $converter = $this->markdownConverter();
        $html = (string) $converter->convert($content);

        // Inject heading IDs and build TOC from h2/h3
        [$htmlWithIds, $toc] = $this->injectHeadingIdsAndToc($html);

        // Enhance links and images
        $htmlWithLinks = $this->postProcessLinksAndImages($htmlWithIds, $locale, $slug);

        $title = $meta['title'] ?? $this->extractH1($htmlWithLinks) ?? $this->inferTitle($path);

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
                    $chunks = $this->extractChunks($data['html'], $item['slug'], $data['title']);
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

    /**
     * Extract chunks of content from HTML for better search indexing.
     * Each chunk represents a section of the document, typically divided by headings.
     *
     * @return array<int, array{id:string, slug:string, title:string, heading:?string, content:string, anchor:?string}>
     */
    protected function extractChunks(string $html, string $slug, string $title): array
    {
        $dom = $this->createDomDocument($html);
        $xpath = new \DOMXPath($dom);

        // Find all headings from h1 to h6
        $nodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

        $chunks = [];

        // First, handle content BEFORE the first heading if any
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $preContentNodes = [];
            $curr = $body->firstChild;
            while ($curr && ! in_array(strtolower($curr->nodeName), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $preContentNodes[] = $curr;
                $curr = $curr->nextSibling;
            }

            if (! empty($preContentNodes)) {
                $content = '';
                foreach ($preContentNodes as $node) {
                    $content .= $dom->saveHTML($node);
                }
                $plainContent = trim(strip_tags($content));
                $plainContent = (string) preg_replace('/\s+/', ' ', $plainContent);

                if ($plainContent !== '') {
                    $chunks[] = [
                        'id' => $slug,
                        'slug' => $slug,
                        'title' => $title,
                        'heading' => null,
                        'content' => $plainContent,
                        'anchor' => null,
                    ];
                }
            }
        }

        // Now handle content starting from each heading
        if ($nodes) {
            foreach ($nodes as $index => $node) {
                if (! ($node instanceof \DOMElement)) {
                    continue;
                }

                $headingText = trim($node->textContent);
                $headingId = $node->getAttribute('id');

                $contentNodes = [];
                $curr = $node->nextSibling;
                while ($curr && ! in_array(strtolower($curr->nodeName), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $contentNodes[] = $curr;
                    $curr = $curr->nextSibling;
                }

                $content = '';
                foreach ($contentNodes as $cNode) {
                    $content .= $dom->saveHTML($cNode);
                }
                $plainContent = trim(strip_tags($content));
                $plainContent = (string) preg_replace('/\s+/', ' ', $plainContent);

                // Even if content is empty (except for the heading itself), we index it
                $chunks[] = [
                    'id' => $slug.($headingId ? '#'.$headingId : '#'.($index + 1)),
                    'slug' => $slug,
                    'title' => $title,
                    'heading' => $headingText,
                    'content' => $plainContent,
                    'anchor' => $headingId ?: null,
                ];
            }
        }

        return $chunks;
    }

    protected function markdownConverter(): MarkdownConverter
    {
        // Create environment without custom config first so extensions can register
        // their schema before we merge extension-specific config. Merging config
        // too early can trigger Nette schema validation for unknown keys.
        $env = new Environment;
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);

        // Register Shiki for syntax highlighting
        $env->addExtension(new HighlightCodeExtension('github-dark'));

        // Register HeadingPermalinkExtension so its schema is available before
        // merging config. This prevents Nette/League config validation errors
        // when providing heading_permalink options.
        $env->addExtension(new HeadingPermalinkExtension);

        // Register Admonition extension for ::: tip, ::: warning, ::: danger
        $env->addExtension(new AdmonitionExtension);

        // Register custom components (tabs, accordion)
        $env->addExtension(new ComponentExtension);

        // Merge heading_permalink config after extensions have been added so the
        // HeadingPermalinkExtension can register its schema first.
        // Note: Environment::mergeConfig is deprecated but acceptable here to
        // ensure schema-aware merging order.
        // phpcs:ignore SlevomatCodingStandard.Commenting.Todo.CommentFound
        // @phpstan-ignore-next-line -- merging config after extensions are registered to avoid validation errors
        $env->mergeConfig([
            'heading_permalink' => [
                'symbol' => '#',
            ],
        ]);

        return new MarkdownConverter($env);
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

    protected function extractH1(string $html): ?string
    {
        $dom = $this->createDomDocument($html);
        $xpath = new \DOMXPath($dom);

        $node = $xpath->query('//h1')->item(0);
        if (! $node instanceof \DOMElement) {
            return null;
        }

        /** @var \DOMElement $node */

        // Remove any permalink anchors inserted by HeadingPermalinkExtension
        $anchors = $node->getElementsByTagName('a');
        /** @var \DOMElement $a */
        foreach ($anchors as $a) {
            // getElementsByTagName yields DOMElement nodes for 'a' tags
            $class = $a->getAttribute('class');
            if ($class !== '' && str_contains($class, 'heading-permalink')) {
                if ($a->parentNode instanceof \DOMNode) {
                    $a->parentNode->removeChild($a);
                }
            }
        }

        $text = trim($node->textContent);

        return $text !== '' ? $text : null;
    }

    /**
     * @return array{0:string,1:array<int,array{level:int,id:string,text:string}>}
     */
    protected function injectHeadingIdsAndToc(string $html): array
    {
        $dom = $this->createDomDocument($html);
        $xpath = new \DOMXPath($dom);

        $toc = [];
        $usedIds = [];

        // Get all h2 and h3 elements in document order
        $nodes = $xpath->query('//h2 | //h3');
        if ($nodes) {
            foreach ($nodes as $node) {
                if (! ($node instanceof \DOMElement)) {
                    continue;
                }

                // Clone the heading node and strip any permalink anchors so the
                // TOC entry text doesn't include the permalink symbol (e.g. '#').
                $nodeClone = $node->cloneNode(true);

                // Clone node and remove any permalink anchors from the clone.
                // cloneNode returns a DOMNode, but we know we're cloning an element.
                /** @var \DOMElement $nodeClone */
                $anchors = $nodeClone->getElementsByTagName('a');
                /** @var \DOMElement $a */
                foreach ($anchors as $a) {
                    $class = $a->getAttribute('class');
                    if ($class !== '' && str_contains($class, 'heading-permalink')) {
                        if ($a->parentNode instanceof \DOMNode) {
                            $a->parentNode->removeChild($a);
                        }
                    }
                }

                $text = trim($nodeClone->textContent);
                $id = Str::slug($text);
                $level = (int) substr($node->tagName, 1); // Extract level from tag name (h2 -> 2, h3 -> 3)

                // Ensure unique IDs
                $originalId = $id;
                $counter = 1;
                while (isset($usedIds[$id])) {
                    $id = $originalId.'-'.$counter++;
                }
                $usedIds[$id] = true;

                $node->setAttribute('id', $id);

                $toc[] = [
                    'level' => $level,
                    'id' => $id,
                    'text' => $text,
                ];
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $innerHtml = '';
        if ($body instanceof \DOMElement) {
            foreach ($body->childNodes as $child) {
                $innerHtml .= $dom->saveHTML($child);
            }
        }

        return [$innerHtml, $toc];
    }

    protected function postProcessLinksAndImages(string $html, string $locale, string $currentSlug): string
    {
        // Add rel/target to external links and rewrite relative doc links
        $html = preg_replace_callback('/<a\s+([^>]*href=\"[^\"]+\"[^>]*)>/i', function ($m) use ($locale, $currentSlug) {
            $tag = $m[0];
            if (preg_match('/href=\"([^\"]+)\"/i', $tag, $hrefMatch)) {
                $href = $hrefMatch[1];

                // External links
                if (Str::startsWith($href, ['http://', 'https://'])) {
                    if (! Str::contains($tag, 'rel=')) {
                        $tag = str_replace('<a ', '<a rel="noopener noreferrer" ', $tag);
                    }
                    if (! Str::contains($tag, 'target=')) {
                        $tag = str_replace('<a ', '<a target="_blank" ', $tag);
                    }
                }
                // Relative markdown links
                elseif (Str::endsWith($href, '.md') || (Str::startsWith($href, './') && ! Str::contains($href, '.'))) {
                    $newUrl = $this->resolveRelativeDocLink($href, $currentSlug, $locale);
                    $tag = str_replace('href="'.$href.'"', 'href="'.$newUrl.'"', $tag);
                }
            }

            return $tag;
        }, $html) ?? $html;

        // Images: make src absolute to /storage or /docs assets path if needed
        $html = preg_replace_callback('/<img\s+([^>]*src=\"[^\"]+\"[^>]*)>/i', function ($m) {
            $tag = $m[0];

            return $tag;
        }, $html) ?? $html;

        // Note: Code block highlighting is now handled by the Shiki extension in markdownConverter()

        return $html;
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
     * @return array{0: array<int,array{locale:string,label:string,url:string,active:bool}>, 1: string}
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

    /**
     * Create a DOMDocument and load HTML safely without affecting global error handling
     */
    protected function createDomDocument(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;

        // Check if we're in a testing environment
        $isTesting = false;
        try {
            $isTesting = app()->environment('testing');
        } catch (\Throwable) {
            // If app() is not available, assume we're not in testing
            $isTesting = false;
        }

        if ($isTesting) {
            // In testing, use libxml flags to suppress errors without affecting global error handling
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } else {
            // In production, use @ suppression which is safe for non-test environments
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        }

        return $dom;
    }
}
