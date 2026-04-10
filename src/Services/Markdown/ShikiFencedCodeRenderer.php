<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer as BaseFencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;
use Spatie\CommonMarkShikiHighlighter\ShikiHighlighter;

/**
 * Wraps the Spatie Shiki renderer to ensure a <pre> wrapper is always present.
 *
 * When Node.js / the shiki npm package are unavailable in production, the
 * upstream FencedCodeRenderer returns only the inner <code> element (no <pre>)
 * as its fallback. Without <pre>, `white-space: pre` is never applied and all
 * newlines collapse, rendering the code block as a single line.
 *
 * This renderer delegates to Shiki when it works, and falls back to the
 * standard CommonMark <pre><code> output otherwise.
 */
class ShikiFencedCodeRenderer implements NodeRendererInterface
{
    protected ShikiHighlighter $highlighter;

    protected BaseFencedCodeRenderer $baseRenderer;

    public function __construct(ShikiHighlighter $highlighter)
    {
        $this->highlighter = $highlighter;
        $this->baseRenderer = new BaseFencedCodeRenderer;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        $element = $this->baseRenderer->render($node, $childRenderer);

        $highlighted = $this->highlighter->highlight(
            (string) $element->getContents(),
            $this->getSpecifiedLanguage($node)
        );

        // Shiki returned a complete <pre …> block — use it directly.
        if (str_starts_with(ltrim($highlighted), '<pre')) {
            return $highlighted;
        }

        // Shiki was unavailable; fall back to the CommonMark <pre><code> output
        // so that white-space is preserved and line breaks render correctly.
        $element->setContents($highlighted);

        return (string) $element;
    }

    protected function getSpecifiedLanguage(FencedCode $block): ?string
    {
        $infoWords = $block->getInfoWords();

        if (empty($infoWords) || empty($infoWords[0])) {
            return null;
        }

        return Xml::escape($infoWords[0]);
    }
}
