<?php

namespace Xoshbin\Pertuk\Extensions\Admonition;

use League\CommonMark\Node\Block\AbstractBlock;

class Admonition extends AbstractBlock
{
    public function __construct(protected string $type)
    {
        parent::__construct();
    }

    public function getType(): string
    {
        return $this->type;
    }
}
