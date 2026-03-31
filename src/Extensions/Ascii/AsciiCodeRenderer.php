<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Extensions\Ascii;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Exception\InvalidArgumentException;

final class AsciiCodeRenderer implements NodeRendererInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
    {
        if (!$node instanceof FencedCode) {
            throw new InvalidArgumentException('Incompatible node type: ' . get_class($node));
        }

        $info = trim($node->getInfo() ?? '');
        
        // Only handle code blocks with the 'ascii' language tag
        if ($info !== 'ascii' && $info !== 'ascii-art') {
            // Not ASCII art, returning null allows CommonMark to try the next renderer (e.g. Shiki)
            return null;
        }

        $content = $node->getLiteral();
        
        $attrs = [
            'class' => 'ascii-art-container',
            'data-language' => 'ascii',
        ];
        
        // Wrap it in <pre> with a special class for CSS
        return new HtmlElement(
            'pre',
            $attrs,
            new HtmlElement('code', ['class' => 'ascii-art'], htmlspecialchars($content))
        );
    }
}
