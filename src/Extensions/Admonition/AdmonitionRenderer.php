<?php

namespace Xoshbin\Pertuk\Extensions\Admonition;

use League\CommonMark\Exception\InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class AdmonitionRenderer implements NodeRendererInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof Admonition) {
            throw new InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        $type = $node->getType();
        $attrs = $node->data->get('attributes');
        $attrs['class'] = trim(($attrs['class'] ?? '')." admonition admonition-{$type}");

        return new HtmlElement(
            'div',
            $attrs,
            $childRenderer->renderNodes($node->children())
        );
    }
}
