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
use League\CommonMark\MarkdownConverter;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class DocumentationService
{
    public function __construct(
        protected string $root,
        protected int $ttl,
        /** @var array<int,string> */
        protected array $exclude = [],
    ) {}

    public static function make(): self
    {
        $root = config('pertuk.root', base_path('docs'));
        $ttl = (int) config('pertuk.cache_ttl', 3600);
        $exclude = (array) config('pertuk.exclude', []);

        return new self($root, $ttl, $exclude);
    }

    /**
     * List docs as a flat array with minimal metadata (for index/sidebar).
     * Prioritizes documents in the current application locale.
     *
     * @return array<string, array{slug:string,title:string,order:int,path:string,mtime:int}>
     */
    public function list(): array
    {
        $files = collect(File::allFiles($this->root))
            ->filter(fn ($file) => Str::endsWith($file->getFilename(), '.md'))
            ->reject(function ($file) {
                $relPath = Str::after($file->getPathname(), rtrim($this->root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

                // Check if filename is in exclude list
                if (in_array($file->getFilename(), $this->exclude, true)) {
                    return true;
                }

                // Check if any part of the path is in exclude list
                foreach ($this->exclude as $excludePattern) {
                    if (Str::contains($relPath, $excludePattern)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        $currentLocale = app()->getLocale();
        $seenBaseSlugs = [];

        foreach ($files as $file) {
            $relPath = Str::after($file->getPathname(), rtrim($this->root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
            $slug = Str::of($relPath)->replaceLast('.md', '')->replace(DIRECTORY_SEPARATOR, '/')->toString();

            // Determine the locale and base slug for this document
            $docLocale = 'en'; // default
            $baseSlug = $slug;

            if (Str::endsWith($slug, '.ar')) {
                $docLocale = 'ar';
                $baseSlug = Str::beforeLast($slug, '.ar');
            } elseif (Str::endsWith($slug, '.ckb')) {
                $docLocale = 'ckb';
                $baseSlug = Str::beforeLast($slug, '.ckb');
            }

            $parsed = $this->parseFrontMatter($file->getPathname());
            $title = $parsed['title'] ?? $this->inferTitle($file->getPathname());
            $order = (int) ($parsed['order'] ?? config('pertuk.default_order', 1000));

            $item = [
                'slug' => $slug,
                'title' => $title,
                'order' => $order,
                'path' => $file->getPathname(),
                'mtime' => File::lastModified($file->getPathname()),
            ];

            // If we haven't seen this base slug yet, or this is the preferred locale, add/replace it
            if (! isset($seenBaseSlugs[$baseSlug]) || $docLocale === $currentLocale) {
                $seenBaseSlugs[$baseSlug] = $item;
            }
        }

        return collect($seenBaseSlugs)
            ->sortBy(['order', 'title'])
            ->mapWithKeys(function ($item) {
                return [$item['slug'] => $item];
            })
            ->all();
    }

    /**
     * Get a single document by slug.
     *
     * @return array{title:string, html:string, toc:array<int,array{level:int,id:string,text:string}>, breadcrumbs:array<int,array{title:string,slug:?string}>, mtime:int, etag:string}
     *
     * @throws FileNotFoundException
     */
    public function get(string $slug): array
    {
        $path = $this->resolvePathFromSlug($slug);
        if (! $path || ! File::exists($path)) {
            throw new FileNotFoundException("Doc not found for slug: {$slug}");
        }

        $mtime = File::lastModified($path);
        $cacheKey = 'pertuk:docs:'.md5($path.':'.$mtime);

        // Get cached value and validate it
        $cached = Cache::get($cacheKey);
        if ($cached !== null && $this->isValidCachedDocument($cached)) {
            return $cached;
        }

        // Determine if we fell back to a base file
        $actualSlug = $this->getSlugFromPath($path);

        // Generate fresh content and cache it
        $result = $this->generateDocumentData($path, $actualSlug, $mtime);
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
    private function generateDocumentData(string $path, string $slug, int $mtime): array
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
            $htmlWithLinks = $this->postProcessLinksAndImages($htmlWithIds, $slug);

            $title = $meta['title'] ?? $this->extractH1($htmlWithLinks) ?? $this->inferTitle($path);

            // Remove first H1 from the content to avoid duplicated page title in the view
            $htmlWithLinks = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $htmlWithLinks, 1) ?? $htmlWithLinks;

            $breadcrumbs = [
                ['title' => __('Documentation'), 'slug' => null],
            ];

            $etag = 'W/"'.substr(sha1($path.'|'.$mtime.'|'.strlen($htmlWithLinks)), 0, 27).'"';

            // Language alternates
            [$alternates, $currentLocale] = $this->buildAlternates($slug);

            return [
                'title' => $title,
                'html' => $htmlWithLinks,
                'toc' => $toc,
                'breadcrumbs' => $breadcrumbs,
                'mtime' => $mtime,
                'etag' => $etag,
                'alternates' => $alternates,
                'current_locale' => $currentLocale,
            ];
    }

    /** Build a lightweight search index (title + headings + plain text excerpt). */
    /** @return array<int, array{slug:string,title:string,headings:array<int,string>,excerpt:string}> */
    public function buildIndex(): array
    {
        return collect($this->list())->map(function ($item) {
            $data = $this->get($item['slug']);

            return [
                'slug' => $item['slug'],
                'title' => $data['title'],
                'headings' => collect($data['toc'])->pluck('text')->toArray(),
                'excerpt' => Str::limit(strip_tags($data['html']), 200),
            ];
        })->values()->all();
    }

    protected function markdownConverter(): MarkdownConverter
    {
        $env = new Environment([
            'heading_permalink' => [
                'symbol' => '#',
            ],
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);

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
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $html, $m)) {
            return trim(strip_tags($m[1]));
        }

        return null;
    }

    /**
     * @return array{0:string,1:array<int,array{level:int,id:string,text:string}>}
     */
    protected function injectHeadingIdsAndToc(string $html): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($dom);

        $toc = [];
        $usedIds = [];

        // Get all h2 and h3 elements in document order
        $nodes = $xpath->query('//h2 | //h3');
        if ($nodes) {
            foreach ($nodes as $node) {
                if (!($node instanceof \DOMElement)) {
                    continue;
                }

                $text = trim($node->textContent);
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

    protected function postProcessLinksAndImages(string $html, string $currentSlug): string
    {
        // Add rel/target to external links and rewrite relative doc links
        $html = preg_replace_callback('/<a\s+([^>]*href=\"[^\"]+\"[^>]*)>/i', function ($m) use ($currentSlug) {
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
                    $newUrl = $this->resolveRelativeDocLink($href, $currentSlug);
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

        // Add highlight.js class to code blocks (append if class exists, otherwise add)
        $html = preg_replace('/<pre><code\s+class=\"([^\"]*)\"/i', '<pre><code class=\"$1 hljs\"', $html) ?? $html;
        $html = preg_replace('/<pre><code(?![^>]*class=)/i', '<pre><code class=\"hljs\"', $html) ?? $html;

        return $html;
    }

    protected function resolvePathFromSlug(string $slug): ?string
    {
        // First, try the exact slug as requested
        $candidate = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $slug).'.md';
        if (File::exists($candidate)) {
            return $candidate;
        }

        // If the slug has a locale suffix (e.g., .ckb, .ar), try falling back to the base version
        if (Str::contains($slug, '.')) {
            $basePath = Str::beforeLast($slug, '.');
            $baseCandidate = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $basePath).'.md';
            if (File::exists($baseCandidate)) {
                return $baseCandidate;
            }
        }

        // Future: locale-aware resolution like docs/{locale}/{slug}.md
        return null;
    }

    /**
     * Convert a file path back to a slug
     */
    protected function getSlugFromPath(string $path): string
    {
        // Remove the root directory and .md extension
        $relativePath = Str::after($path, rtrim($this->root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $slug = Str::beforeLast($relativePath, '.md');

        // Convert directory separators to forward slashes for URL
        return str_replace(DIRECTORY_SEPARATOR, '/', $slug);
    }

    protected function resolveRelativeDocLink(string $href, string $currentSlug): string
    {
        // Resolve relative path against current slug path
        $currentDir = Str::of($currentSlug)->contains('/') ? Str::beforeLast($currentSlug, '/') : '';
        $target = ltrim($href, './');
        if (Str::endsWith($target, '.md')) {
            $target = Str::beforeLast($target, '.md');
        }
        $slug = trim($currentDir ? ($currentDir.'/'.$target) : $target, '/');

        return url('/docs/'.$slug);
    }

    /**
     * Build language alternate links for a given slug based on available files.
     *
     * @return array{0: array<int,array{locale:string,label:string,url:string,active:bool}>, 1: string}
     */
    protected function buildAlternates(string $slug): array
    {
        // Determine base slug (strip locale suffix like .ar or .ckb)
        $currentLocale = 'en';
        $baseSlug = $slug;

        if (Str::endsWith($slug, '.ar')) {
            $currentLocale = 'ar';
            $baseSlug = Str::beforeLast($slug, '.ar');
        } elseif (Str::endsWith($slug, '.ckb')) {
            $currentLocale = 'ckb';
            $baseSlug = Str::beforeLast($slug, '.ckb');
        }

        $locales = [
            'en' => 'English',
            'ar' => 'العربية',
            'ckb' => 'کوردی',
        ];

        $candidates = [
            'en' => $baseSlug,
            'ar' => $baseSlug.'.ar',
            'ckb' => $baseSlug.'.ckb',
        ];

        $alternates = [];
        foreach ($candidates as $locale => $candidateSlug) {
            $path = $this->resolvePathFromSlug($candidateSlug);
            if ($path) {
                $alternates[] = [
                    'locale' => $locale,
                    'label' => $locales[$locale],
                    'url' => url('/docs/'.$candidateSlug),
                    'active' => $locale === $currentLocale,
                ];
            }
        }

        return [$alternates, $currentLocale];
    }
}
