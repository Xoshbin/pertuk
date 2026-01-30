<?php

namespace Xoshbin\Pertuk\Extensions\Admonition;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

class AdmonitionContainerParser extends AbstractBlockContinueParser
{
    protected Admonition $block;

    public function __construct(string $type)
    {
        $this->block = new Admonition($type);
    }

    public function getBlock(): Admonition
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($cursor->match('/^:::/')) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
