<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

class ContentProcessor
{
    /**
     * @return array{0: string, 1: array<int, array{level: int, id: string, text: string}>}
     */
    public function injectHeadingIdsAndToc(string $html): array
    {
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);

        $toc = [];
        $usedIds = [];

        // Get all h2 and h3 elements in document order
        $nodes = $xpath->query('//h2 | //h3');

        if ($nodes) {
            foreach ($nodes as $node) {
                if (! ($node instanceof DOMElement)) {
                    continue;
                }

                // Clone the heading node and strip any permalink anchors so the
                // TOC entry text doesn't include the permalink symbol (e.g. '#').
                $nodeClone = $node->cloneNode(true);

                // Clone node and remove any permalink anchors from the clone.
                // cloneNode returns a DOMNode, but we know we're cloning an element.
                /** @var DOMElement $nodeClone */
                $anchors = $nodeClone->getElementsByTagName('a');
                /** @var DOMElement $a */
                foreach ($anchors as $a) {
                    $class = $a->getAttribute('class');
                    if ($class !== '' && str_contains($class, 'heading-permalink')) {
                        if ($a->parentNode instanceof DOMNode) {
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
        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $child) {
                $innerHtml .= $dom->saveHTML($child);
            }
        } else {
            // If body is missing, it implies empty or malformed content that resulted in no body tag.
            // Returning empty string is safer than dumping the whole DOM with DOCTYPE.
            $innerHtml = '';
        }

        return [$innerHtml, $toc];
    }

    public function extractH1(string $html): ?string
    {
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);

        $node = $xpath->query('//h1')->item(0);
        if (! $node instanceof DOMElement) {
            return null;
        }

        /** @var DOMElement $node */
        // Remove any permalink anchors inserted by HeadingPermalinkExtension
        $anchors = $node->getElementsByTagName('a');
        /** @var DOMElement $a */
        foreach ($anchors as $a) {
            // getElementsByTagName yields DOMElement nodes for 'a' tags
            $class = $a->getAttribute('class');
            if ($class !== '' && str_contains($class, 'heading-permalink')) {
                if ($a->parentNode instanceof DOMNode) {
                    $a->parentNode->removeChild($a);
                }
            }
        }

        $text = trim($node->textContent);

        return $text !== '' ? $text : null;
    }

    public function postProcessLinksAndImages(string $html, string $locale, string $currentSlug, ?string $version = null): string
    {
        // Add rel/target to external links and rewrite relative doc links
        $html = preg_replace_callback('/<a\s+([^>]*href=\"[^\"]+\"[^>]*)>/i', function ($m) use ($locale, $currentSlug, $version) {
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
                    $newUrl = $this->resolveRelativeDocLink($href, $currentSlug, $locale, $version);
                    $tag = str_replace('href="'.$href.'"', 'href="'.$newUrl.'"', $tag);
                }
            }

            return $tag;
        }, $html) ?? $html;

        // Images: make src absolute to /storage or /docs assets path if needed
        // Currently just returning tag as-is but placeholder for future logic
        $html = preg_replace_callback('/<img\s+([^>]*src=\"[^\"]+\"[^>]*)>/i', function ($m) {
            $tag = $m[0];

            return $tag;
        }, $html) ?? $html;

        return $html;
    }

    /**
     * @return array<int, array{id: string, slug: string, title: string, heading: ?string, content: string, anchor: ?string}>
     */
    public function extractChunks(string $html, string $slug, string $title): array
    {
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);

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
                if (! ($node instanceof DOMElement)) {
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

    protected function resolveRelativeDocLink(string $href, string $currentSlug, string $locale, ?string $version = null): string
    {
        // Resolve relative path against current slug path
        $currentDir = Str::of($currentSlug)->contains('/') ? Str::beforeLast($currentSlug, '/') : '';
        $target = ltrim($href, './');
        if (Str::endsWith($target, '.md')) {
            $target = Str::beforeLast($target, '.md');
        }
        $slug = trim($currentDir ? ($currentDir.'/'.$target) : $target, '/');

        return url('/'.config('pertuk.route_prefix', 'docs').'/'.($version ? $version.'/' : '').$locale.'/'.$slug);
    }

    protected function createDomDocument(string $html): DOMDocument
    {
        $dom = new DOMDocument;

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
