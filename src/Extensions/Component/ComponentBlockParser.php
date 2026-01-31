<?php

namespace Xoshbin\Pertuk\Extensions\Component;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

class ComponentBlockParser extends AbstractBlockContinueParser
{
    protected ComponentBlock $block;

    public function __construct(string $tagName, array $attributes = [])
    {
        $this->block = new ComponentBlock($tagName, $attributes);
    }

    public function getBlock(): ComponentBlock
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
        // If we find the closing tag for this exact tag
        $tagName = $this->block->getTagName();
        if ($cursor->match('/^<\/'.preg_quote($tagName).'>/')) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
