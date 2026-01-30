<?php

namespace Xoshbin\Pertuk\Extensions\Component;

use League\CommonMark\Node\Block\AbstractBlock;

class ComponentBlock extends AbstractBlock
{
    public function __construct(
        protected string $tagName,
        protected array $attributes = []
    ) {}

    public function getTagName(): string
    {
        return $this->tagName;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }
}
