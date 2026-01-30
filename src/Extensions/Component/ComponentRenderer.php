<?php

namespace Xoshbin\Pertuk\Extensions\Component;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class ComponentRenderer implements NodeRendererInterface
{
    /**
     * @param  ComponentBlock  $node
     *
     * {@inheritDoc}
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        ComponentBlock::assertInstanceOf($node);

        $attrs = $node->getAttributes();
        $tagName = $node->getTagName();

        if ($tagName === 'x-pertuk-tabs') {
            $attrs['x-data'] = 'pertukTabs';
            $attrs['class'] = trim(($attrs['class'] ?? '').' pertuk-tabs-container');
        } elseif ($tagName === 'x-pertuk-accordion') {
            $attrs['x-data'] = 'pertukAccordion';
            $attrs['class'] = trim(($attrs['class'] ?? '').' pertuk-accordion-container');
        }

        return new HtmlElement(
            $tagName,
            $attrs,
            $childRenderer->renderNodes($node->children())
        );
    }
}
