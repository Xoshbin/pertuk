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
            $supported = (array) config('pertuk.supported_locales', ['en']);
            $default = config('pertuk.default_locale', 'en');
            $docLocale = $default;
            $baseSlug = $slug;

            foreach ($supported as $loc) {
                if ($loc === $default) {
                    continue;
                }
                if (Str::endsWith($slug, '.'.$loc)) {
                    $docLocale = $loc;
                    $baseSlug = Str::beforeLast($slug, '.'.$loc);
                    break;
                }
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

    /** Build a lightweight search index with content chunks for better relevancy. */
    /** @return array<int, array{id:string, slug:string, title:string, heading:?string, content:string, anchor:?string}> */
    public function buildIndex(): array
    {
        $index = [];
        foreach ($this->list() as $item) {
            try {
                $data = $this->get($item['slug']);
                $chunks = $this->extractChunks($data['html'], $item['slug'], $data['title']);
                foreach ($chunks as $chunk) {
                    $index[] = $chunk;
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to index doc: {$item['slug']}", ['e' => $e->getMessage()]);
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

        // Note: Code block highlighting is now handled by the Shiki extension in markdownConverter()

        return $html;
    }

    protected function resolvePathFromSlug(string $slug): ?string
    {
        // The slug already includes the file extension, so we just need to replace the directory separators.
        $candidate = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $slug);
        if (! Str::endsWith($candidate, '.md')) {
            $candidate .= '.md';
        }

        if (File::exists($candidate)) {
            return $candidate;
        }

        // If the slug has a locale suffix (e.g., .ckb, .ar), try falling back to the base version
        if (Str::contains($slug, '.')) {
            $baseSlug = Str::beforeLast($slug, '.');
            $baseCandidate = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $baseSlug).'.md';
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

        return url('/'.config('pertuk.route_prefix', 'docs').'/'.$slug);
    }

    /**
     * Build language alternate links for a given slug based on available files.
     *
     * @return array{0: array<int,array{locale:string,label:string,url:string,active:bool}>, 1: string}
     */
    protected function buildAlternates(string $slug): array
    {
        // Determine base slug and current locale dynamically based on configured locales
        $supported = (array) config('pertuk.supported_locales', ['en']);
        $default = config('pertuk.default_locale', 'en');
        $currentLocale = $default;
        $baseSlug = $slug;

        foreach ($supported as $loc) {
            if ($loc === $default) {
                continue;
            }
            if (Str::endsWith($slug, '.'.$loc)) {
                $currentLocale = $loc;
                $baseSlug = Str::beforeLast($slug, '.'.$loc);
                break;
            }
        }

        $labels = (array) config('pertuk.locale_labels', []);
        $prefix = config('pertuk.route_prefix', 'docs');

        $alternates = [];
        foreach ($supported as $loc) {
            $candidateSlug = $loc === $default ? $baseSlug : $baseSlug.'.'.$loc;
            $path = $this->resolvePathFromSlug($candidateSlug);
            if ($path) {
                $alternates[] = [
                    'locale' => $loc,
                    'label' => $labels[$loc] ?? strtoupper($loc),
                    'url' => url('/'.$prefix.'/'.$candidateSlug),
                    'active' => $loc === $currentLocale,
                ];
            }
        }

        return [$alternates, $currentLocale];
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
